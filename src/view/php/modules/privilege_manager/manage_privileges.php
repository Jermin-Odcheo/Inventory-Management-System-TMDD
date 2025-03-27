<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');
include '../../general/header.php';

// Query to fetch modules with their module-level privileges (role_id = 0)
$sql = "
SELECT 
    m.id AS Module_ID,
    m.module_name AS Module_Name,
    COALESCE((
      SELECT GROUP_CONCAT(p.priv_name ORDER BY p.priv_name SEPARATOR ', ')
      FROM role_module_privileges rmp
      JOIN privileges p ON p.id = rmp.privilege_id
      WHERE rmp.module_id = m.id AND rmp.role_id = 0
    ), 'No privileges') AS Privileges
FROM modules m
ORDER BY m.id
";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch available privileges that are enabled (is_disabled = 0)
$stmt = $pdo->query("SELECT id, priv_name FROM privileges WHERE is_disabled = 0 ORDER BY priv_name");
$privileges = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Module Privilege Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar with fixed width */
        .sidebar {
            width: 300px;
            background-color: #2c3e50;
            color: #fff;
        }

        /* Main content takes remaining space */
        .main-content {
            flex: 1;
            padding: 20px;
            margin-left: 300px;
        }

        /* (Optional) Override container-fluid padding if needed */
        .main-content.container-fluid {
            padding: 100px 15px;
        }
    </style>
</head>
<body>
<div class="wrapper">
    <!-- Sidebar -->
    <div class="sidebar">
        <?php include '../../general/sidebar.php'; ?>
    </div>

    <!-- Main Content Area -->
    <div class="main-content container-fluid">
        <h1>Module Privilege Management</h1>

        <!-- Add Module Button -->
        <div class="d-flex justify-content-end mb-3">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModuleModal">
                Create Module
            </button>
        </div>

        <div class="table-responsive" id="table">
            <table id="privilegeTable" class="table table-striped table-hover">
                <thead class="table-dark">
                <tr>
                    <th>Module ID</th>
                    <th>Module Name</th>
                    <th>Privileges</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!empty($modules)): ?>
                    <?php foreach ($modules as $module): ?>
                        <tr data-module-id="<?php echo $module['Module_ID']; ?>">
                            <td><?php echo htmlspecialchars($module['Module_ID']); ?></td>
                            <td><?php echo htmlspecialchars($module['Module_Name']); ?></td>
                            <td><?php echo htmlspecialchars($module['Privileges']); ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-warning edit-module-btn"
                                        data-module-id="<?php echo $module['Module_ID']; ?>"
                                        data-bs-toggle="modal" data-bs-target="#editModuleModal">
                                    Edit Privileges
                                </button>
                                <button type="button" class="btn btn-sm btn-danger delete-module-btn"
                                        data-module-id="<?php echo $module['Module_ID']; ?>">
                                    Remove Module
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4">No modules found.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
            <!-- Pagination Controls (optional) -->
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
                            <button id="prevPage"
                                    class="btn btn-outline-primary d-flex align-items-center gap-1">
                                <i class="bi bi-chevron-left"></i> Previous
                            </button>
                            <select id="rowsPerPageSelect" class="form-select" style="width: auto;">
                                <option value="10" selected>10</option>
                                <option value="20">20</option>
                                <option value="30">30</option>
                                <option value="50">50</option>
                            </select>
                            <button id="nextPage"
                                    class="btn btn-outline-primary d-flex align-items-center gap-1">
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
        </div><!-- .table-responsive -->

    </div><!-- .main-content -->
</div><!-- .wrapper -->

<script type="text/javascript" src="<?php echo BASE_URL; ?>src/control/js/pagination.js" defer></script>

<!-- Confirm Delete Modal -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel"
     aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete the module "<span id="roleNamePlaceholder"></span>"?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button id="confirmDeleteButton" type="button" class="btn btn-danger">Delete</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Module Modal -->
<div class="modal fade" id="addModuleModal" tabindex="-1" aria-labelledby="addModuleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="addModuleForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="addModuleModalLabel">Create Module</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Module Name -->
                    <div class="mb-3">
                        <label for="moduleName" class="form-label">Module Name</label>
                        <input type="text" class="form-control" id="moduleName" name="module_name" required>
                    </div>
                    <!-- Privileges as Checkboxes -->
                    <div class="mb-3">
                        <label class="form-label">Select Privileges (Optional)</label>
                        <?php if (!empty($privileges)): ?>
                            <?php foreach ($privileges as $privilege): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="privileges[]"
                                           value="<?php echo htmlspecialchars($privilege['id']); ?>"
                                           id="privilege_<?php echo htmlspecialchars($privilege['id']); ?>">
                                    <label class="form-check-label"
                                           for="privilege_<?php echo htmlspecialchars($privilege['id']); ?>">
                                        <?php echo htmlspecialchars($privilege['priv_name']); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No privileges available.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Create Module</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Module Privileges Modal -->
<div class="modal fade" id="editModuleModal" tabindex="-1" aria-labelledby="editModuleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div id="editModuleContent">
                Loading...
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
        // Delegate event binding for edit button to handle dynamically loaded elements.
        $(document).on('click', '.edit-module-btn', function () {
            var moduleID = $(this).data('module-id');
            $('#editModuleContent').html("Loading...");
            $.ajax({
                url: 'edit_module_privileges.php',
                type: 'GET',
                data: {module_id: moduleID},
                headers: {'X-Requested-With': 'XMLHttpRequest'},
                success: function (response) {
                    $('#editModuleContent').html(response);
                },
                error: function (xhr, status, error) {
                    $('#editModuleContent').html('<p class="text-danger">Error loading module privileges. Please try again.</p>');
                }
            });
        });

        // Use delegation for delete button as well.
        var moduleToDelete = null;
        $(document).on('click', '.delete-module-btn', function () {
            var moduleID = $(this).data('module-id');
            var moduleName = $(this).closest('tr').find('td:eq(1)').text();
            moduleToDelete = moduleID;
            $('#roleNamePlaceholder').text(moduleName);
            $('#confirmDeleteModal').modal('show');
        });

        // Confirm delete button click.
        $('#confirmDeleteButton').on('click', function () {
            if (moduleToDelete === null) {
                return;
            }
            $.ajax({
                url: 'delete_module.php',
                type: 'POST',
                data: {module_id: moduleToDelete},
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        // Reload the table after deletion
                        $('#privilegeTable').load(location.href + ' #privilegeTable', function () {
                            showToast(response.message, 'success');
                        });
                        $('#confirmDeleteModal').modal('hide');
                    } else {
                        showToast(response.message, 'error');
                    }
                },
                error: function () {
                    showToast('Error deleting module.', 'error');
                }
            });
        });

        // Handle Add Module form submission.
        $('#addModuleForm').on('submit', function (e) {
            e.preventDefault();
            $.ajax({
                url: 'add_module.php',
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        $('#privilegeTable').load(location.href + ' #privilegeTable', function () {
                            showToast(response.message, 'success');
                        });
                        $('#addModuleModal').modal('hide');
                        // Remove any leftover modal backdrop
                        $('.modal-backdrop').remove();
                    } else {
                        showToast(response.message, 'error');
                    }
                },
                error: function () {
                    showToast('Error adding module.', 'error');
                }
            });
        });

    });
</script>

<?php include '../../general/footer.php'; ?>
</body>
</html>