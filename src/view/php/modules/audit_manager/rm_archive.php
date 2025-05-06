<?php
ob_start();
require_once('../../../../../config/ims-tmdd.php');
session_start();
include '../../general/header.php';
include '../../general/sidebar.php';
include '../../general/footer.php';

// 1) Auth guard
$userId = $_SESSION['user_id'] ?? null;
if (!is_int($userId) && !ctype_digit((string)$userId)) {
    header('Location: ../../../../../public/index.php');
    exit();
}
$userId = (int)$userId;

// 2) Init RBAC & enforce "View"
$rbac = new RBACService($pdo, $_SESSION['user_id']);
$rbac->requirePrivilege('Roles and Privileges', 'View');

// 3) Button flags
$canRestore = $rbac->hasPrivilege('Roles and Privileges', 'Modify');
$canPermanentDelete = $rbac->hasPrivilege('Roles and Privileges', 'Remove');

// SQL query for archived roles
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
WHERE r.is_disabled = 1
ORDER BY r.id, m.id;
";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$roleData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group data by role and module
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
    <title>Archived Roles</title>
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
        .restore-btn, .delete-btn {
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

        .restore-btn {
            color: #4f46e5;
        }

        .delete-btn {
            color: #ef4444;
        }

        .restore-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .delete-btn:hover {
            background-color: #fee2e2;
        }

        .restore-btn:active {
            transform: translateY(0);
        }
    </style>
</head>

<body>
<div class="wrapper">
    <div class="main-content container-fluid">
        <header class="main-header">
            <h1>Archived Roles</h1>
        </header>
        <div class="table-responsive" id="table">
            <table id="archivedRolesTable" class="table table-striped table-hover align-middle">
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
                                <?php if ($canRestore): ?>
                                <button type="button" class="restore-btn restore-role-btn"
                                        data-role-id="<?php echo $roleID; ?>"
                                        data-role-name="<?php echo htmlspecialchars($role['Role_Name']); ?>"
                                        data-bs-toggle="modal" data-bs-target="#confirmRestoreModal">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </button>
                                <?php endif; ?>
                                <?php if ($canPermanentDelete): ?>
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
                        <td colspan="4">No archived roles found.</td>
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

<!-- Modals -->
<div class="modal fade" id="confirmRestoreModal" tabindex="-1" aria-labelledby="confirmRestoreModalLabel"
     aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Restore</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to restore the role "<span id="restoreRoleNamePlaceholder"></span>"?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a id="confirmRestoreButton" href="#" class="btn btn-primary">Restore</a>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel"
     aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Permanent Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-danger fw-bold">Warning: This action cannot be undone!</p>
                Are you sure you want to permanently delete the role "<span id="roleNamePlaceholder"></span>"?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a id="confirmDeleteButton" href="#" class="btn btn-danger">Delete Permanently</a>
            </div>
        </div>
    </div>
</div>

<script>
    // Pass RBAC privileges to JavaScript
    const userPrivileges = {
        canRestore: <?php echo json_encode($canRestore); ?>,
        canPermanentDelete: <?php echo json_encode($canPermanentDelete); ?>
    };

    document.addEventListener('DOMContentLoaded', function () {
        // Handle restore role modal
        $('#confirmRestoreModal').on('show.bs.modal', function (event) {
            if (!userPrivileges.canRestore) {
                event.preventDefault();
                return false;
            }
            
            var button = $(event.relatedTarget);
            var roleID = button.data('role-id');
            var roleName = button.data('role-name');
            $('#restoreRoleNamePlaceholder').text(roleName);
            $('#confirmRestoreButton').data('role-id', roleID);
        });

        // Confirm restore role via AJAX
        $(document).on('click', '#confirmRestoreButton', function (e) {
            if (!userPrivileges.canRestore) return;
            
            e.preventDefault();
            $(this).blur();
            var roleID = $(this).data('role-id');
            $.ajax({
                type: 'POST',
                url: 'restore_role.php',
                data: {id: roleID},
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        $('#archivedRolesTable').load(location.href + ' #archivedRolesTable', function () {
                            updatePagination();
                            showToast(response.message, 'success', 5000);
                        });
                        $('#confirmRestoreModal').modal('hide');
                        $('.modal-backdrop').remove();
                    } else {
                        showToast(response.message || 'An error occurred', 'error', 5000);
                    }
                },
                error: function (xhr, status, error) {
                    showToast('Error restoring role: ' + error, 'error', 5000);
                }
            });
        });

        // Handle permanent delete role modal
        $('#confirmDeleteModal').on('show.bs.modal', function (event) {
            if (!userPrivileges.canPermanentDelete) {
                event.preventDefault();
                return false;
            }
            
            var button = $(event.relatedTarget);
            var roleID = button.data('role-id');
            var roleName = button.data('role-name');
            $('#roleNamePlaceholder').text(roleName);
            $('#confirmDeleteButton').data('role-id', roleID);
        });

        // Confirm permanent delete role via AJAX
        $(document).on('click', '#confirmDeleteButton', function (e) {
            if (!userPrivileges.canPermanentDelete) return;
            
            e.preventDefault();
            $(this).blur();
            var roleID = $(this).data('role-id');
            $.ajax({
                type: 'POST',
                url: 'permanent_delete_role.php',
                data: {id: roleID},
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        $('#archivedRolesTable').load(location.href + ' #archivedRolesTable', function () {
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
                    showToast('Error permanently deleting role: ' + error, 'error', 5000);
                }
            });
        });
    });
</script>
</body>
</html> 