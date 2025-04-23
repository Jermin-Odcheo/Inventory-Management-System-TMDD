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
            background-color: #eef2ff;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .delete-btn:hover {
            background-color: #fee2e2;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .edit-btn:active,
        .delete-btn:active {
            transform: translateY(0);
        }

        /* Modern checkbox design */
        .custom-checkbox .form-check-input {
            border-radius: 0.25rem;
            border: 2px solid #007bff;
            width: 20px;
            height: 20px;
            transition: all 0.3s ease;
        }

        .custom-checkbox .form-check-input:checked {
            background-color: #007bff;
            border-color: #007bff;
        }

        .custom-checkbox .form-check-label {
            margin-left: 10px;
            font-weight: 500;
            font-size: 0.95rem;
            color: #333;
        }

        /* Row layout for checkboxes */
        .row {
            display: flex;
            flex-wrap: wrap;
        }

        .col-12.col-md-6 {
            margin-bottom: 10px;
        }

        /* Hover effect for checkboxes */
        .custom-checkbox .form-check-input:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }

        /* Active state styling for checkboxes */
        .custom-checkbox .form-check-input:active {
            box-shadow: 0 0 0 0.2rem rgba(38, 143, 255, 0.5);
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
                                <button type="button" class="edit-btn edit-module-btn"
                                        data-module-id="<?php echo $module['Module_ID']; ?>"
                                        data-bs-toggle="modal" data-bs-target="#editModuleModal">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <button type="button" class="delete-btn delete-module-btn"
                                        data-module-id="<?php echo $module['Module_ID']; ?>">
                                    <i class="bi bi-trash"></i>
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
                        <div class="row">
                            <?php if (!empty($privileges)): ?>
                                <?php foreach ($privileges as $privilege): ?>
                                    <div class="col-12 col-md-6">
                                        <div class="form-check custom-checkbox">
                                            <input class="form-check-input" type="checkbox" name="privileges[]"
                                                   value="<?php echo htmlspecialchars($privilege['id']); ?>"
                                                   id="privilege_<?php echo htmlspecialchars($privilege['id']); ?>">
                                            <label class="form-check-label"
                                                   for="privilege_<?php echo htmlspecialchars($privilege['id']); ?>">
                                                <?php echo htmlspecialchars($privilege['priv_name']); ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>No privileges available.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Module</button>
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
        $('#addModuleModal').on('hidden.bs.modal', function () {
            $(this).find('form')[0].reset();
        });

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

        var moduleToDelete = null;
        $(document).on('click', '.delete-module-btn', function () {
            var moduleID = $(this).data('module-id');
            var moduleName = $(this).closest('tr').find('td:eq(1)').text();
            moduleToDelete = moduleID;
            $('#roleNamePlaceholder').text(moduleName);
            $('#confirmDeleteModal').modal('show');
        });

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
                        $('#privilegeTable').load(location.href + ' #privilegeTable', function () {
                            updatePagination();
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
                            updatePagination();
                            showToast(response.message, 'success');
                        });
                        $('#addModuleModal').modal('hide');
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

        function showToast(message, type) {
            var toastHTML = '<div class="toast align-items-center text-white bg-' + type + ' border-0" role="alert" aria-live="assertive" aria-atomic="true">'
                + '<div class="d-flex">'
                + '<div class="toast-body">' + message + '</div>'
                + '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>'
                + '</div>'
                + '</div>';
            var toastContainer = $('.toast-container');
            toastContainer.append(toastHTML);
            var toastElement = toastContainer.find('.toast:last-child');
            var toast = new bootstrap.Toast(toastElement[0]);
            toast.show();
            toastElement.on('hidden.bs.toast', function () {
                $(this).remove();
            });
        }
    });
</script>

</body>
</html>
