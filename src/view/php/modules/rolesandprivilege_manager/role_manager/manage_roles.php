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
        .edit-btn, .delete-btn {
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

        <div class="d-flex justify-content-end mb-3">
            <!-- <?php if ($canViewArchive): ?>
            <a href="archived_roles.php" class="btn btn-outline-secondary me-2">
                <i class="bi bi-archive"></i> View Archived Roles
            </a> -->
            <?php endif; ?>
            <?php if ($canUndo): ?>
            <button type="button" class="btn btn-secondary me-2" id="undoButton">Undo</button>
            <?php endif; ?>
            <?php if ($canRedo): ?>
            <button type="button" class="btn btn-secondary" id="redoButton">Redo</button>
            <?php endif; ?>
            <?php if ($canCreate): ?>
            <button type="button" class="btn btn-primary ms-2" data-bs-toggle="modal" data-bs-target="#addRoleModal">
                Create New Role
            </button>
            <?php endif; ?>
        </div>

        <!-- Add Filter Section -->
        <div class="row mb-3">
            <div class="col-md-3">
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
            </div>
        </div>

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
                <tbody>
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
            <div class="container-fluid">
                <div class="row align-items-center g-3">
                    <div class="col-12 col-sm-auto">
                        <div class="text-muted">
                            Showing <span id="currentPage">1</span> to <span id="rowsPerPage">20</span> of <span
                                    id="totalRows">100</span> entries
                        </div>
                    </div>
                    <div class="col-12 col-sm-auto ms-sm-auto">
                        <div class="d-flex align-items-center gap-2">
                            <button id="prevPage" class="btn btn-outline-primary d-flex align-items-center gap-1">
                                <i class="bi bi-chevron-left"></i> Previous
                            </button>
                            <select id="rowsPerPageSelect" class="form-select" style="width: auto;">
                                <option value="10" selected>10</option>
                                <option value="20">20</option>
                                <option value="30">30</option>
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

    document.addEventListener('DOMContentLoaded', function () {
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

            let visibleCount = 0;
            $('#rolesTable tbody tr').each(function() {
                const $row = $(this);
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

                $row.toggle(showRow);
                if (showRow) visibleCount++;
            });

            // Show "no results" message if no matches
            if (visibleCount === 0) {
                if ($('#no-results-row').length === 0) {
                    $('#rolesTable tbody').append(
                        '<tr id="no-results-row"><td colspan="4" class="text-center py-3">' +
                        '<div class="alert alert-info mb-0">' +
                        '<i class="bi bi-info-circle me-2"></i>No matching roles found. Try adjusting your filters.' +
                        '</div></td></tr>'
                    );
                }
            } else {
                $('#no-results-row').remove();
            }

            // Update pagination info
            updatePaginationInfo(visibleCount);
        }

        // Update pagination info
        function updatePaginationInfo(visibleRows) {
            const totalRows = $('#rolesTable tbody tr').length - ($('#no-results-row').length > 0 ? 1 : 0);
            const rowsPerPage = parseInt($('#rowsPerPageSelect').val()) || 10;
            
            $('#totalRows').text(totalRows);
            $('#rowsPerPage').text(Math.min(rowsPerPage, visibleRows));
            $('#currentPage').text(visibleRows > 0 ? '1' : '0');
            
            // Update pagination controls if that function exists
            if (typeof updatePaginationControls === 'function') {
                updatePaginationControls(visibleRows);
            }
        }

        // Add event listeners for filters
        $('#roleNameFilter').on('input', filterTable);
        $('#moduleFilter').on('change', filterTable);
        $('#privilegeFilter').on('change', filterTable);

        // Clear filters button
        $('#clear-filters-btn').on('click', function() {
            $('#roleNameFilter').val('');
            if ($.fn.select2) {
                $('#moduleFilter').val('').trigger('change');
                $('#privilegeFilter').val(null).trigger('change');
            } else {
                $('#moduleFilter').val('');
                $('#privilegeFilter').val([]);
            }
            filterTable();
        });

        // **1. Load edit role modal content via AJAX**
        $(document).on('click', '.edit-role-btn', function () {
            if (!userPrivileges.canModify) return;
            
            var roleID = $(this).data('role-id');
            $('#editRoleContent').html("Loading...");
            $.ajax({
                url: 'edit_roles.php',
                type: 'GET',
                data: {id: roleID},
                headers: {'X-Requested-With': 'XMLHttpRequest'},
                success: function (response) {
                    $('#editRoleContent').html(response);
                    $('#roleID').val(roleID);
                },
                error: function (xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    $('#editRoleContent').html('<p class="text-danger">Error loading role data. Please try again.</p>');
                }
            });
        });

        // **2. Handle delete role modal**
        $('#confirmDeleteModal').on('show.bs.modal', function (event) {
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
        $(document).on('click', '#confirmDeleteButton', function (e) {
            if (!userPrivileges.canRemove) return;
            
            e.preventDefault();
            $(this).blur();
            var roleID = $(this).data('role-id');
            $.ajax({
                type: 'POST',
                url: 'delete_role.php',
                data: {id: roleID},
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        $('#rolesTable').load(location.href + ' #rolesTable', function () {
                            updatePagination();
                            showToast(response.message, 'success', 5000);
                        });
                        $('#confirmDeleteModal').modal('hide');
                        $('.modal-backdrop').remove();
                    } else {
                        showToast(response.message || 'An error occurred', 'error', 5000);
                    }
                },
                error: function (xhr, status, error) {
                    showToast('Error deleting role: ' + error, 'error', 5000);
                }
            });
        });

        // **4. Load add role modal content**
        $('#addRoleModal').on('show.bs.modal', function (event) {
            if (!userPrivileges.canCreate) {
                event.preventDefault();
                return false;
            }
            
            $('#addRoleContent').html("Loading...");
            $.ajax({
                url: 'add_role.php',
                type: 'GET',
                success: function (response) {
                    $('#addRoleContent').html(response);
                },
                error: function () {
                    $('#addRoleContent').html('<p class="text-danger">Error loading form.</p>');
                }
            });
        });

        // **7. Undo button via AJAX**
        $(document).on('click', '#undoButton', function () {
            if (!userPrivileges.canUndo) return;
            
            $.ajax({
                url: 'undo.php',
                type: 'GET',
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        $('#rolesTable').load(location.href + ' #rolesTable', function () {
                            updatePagination();
                            showToast(response.message, 'success', 5000);
                        });
                    } else {
                        showToast(response.message || 'An error occurred', 'error', 5000);
                    }
                },
                error: function (xhr, status, error) {
                    showToast('Error processing undo request: ' + error, 'error', 5000);
                }
            });
        });

        // **8. Redo button via AJAX**
        $(document).on('click', '#redoButton', function () {
            if (!userPrivileges.canRedo) return;
            
            $.ajax({
                url: 'redo.php',
                type: 'GET',
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        $('#rolesTable').load(location.href + ' #rolesTable', function () {
                            updatePagination();
                            showToast(response.message, 'success', 5000);
                        });
                    } else {
                        showToast(response.message || 'An error occurred', 'error', 5000);
                    }
                },
                error: function (xhr, status, error) {
                    showToast('Error processing redo request: ' + error, 'error', 5000);
                }
            });
        });
    });
</script>
</body>
</html>
