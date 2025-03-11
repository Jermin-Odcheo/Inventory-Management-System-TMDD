<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    } else {
        header("Location: login.php");
        exit();
    }
}

// Handle AJAX POST for adding a new module
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['module_name'])) {
    $moduleName = trim($_POST['module_name']);
    if (empty($moduleName)) {
        echo json_encode(['success' => false, 'message' => 'Module name is required.']);
        exit;
    }
    try {
        $stmt = $pdo->prepare("INSERT INTO modules (module_name) VALUES (:module_name)");
        $stmt->execute(['module_name' => $moduleName]);
        $newModuleId = $pdo->lastInsertId();

        // Insert any selected privileges
        if (!empty($_POST['privileges']) && is_array($_POST['privileges'])) {
            $stmtLink = $pdo->prepare("
                INSERT INTO role_module_privileges (module_id, privilege_id)
                VALUES (:module_id, :privilege_id)
            ");
            foreach ($_POST['privileges'] as $privId) {
                $stmtLink->execute(['module_id' => $newModuleId, 'privilege_id' => $privId]);
            }
        }
        echo json_encode(['success' => true, 'message' => 'Module added successfully.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Otherwise, render the page
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT
            m.id AS module_id,
            m.module_name,
            p.id AS privilege_id,
            p.priv_name
        FROM modules m
        LEFT JOIN role_module_privileges mp ON m.id = mp.module_id
        LEFT JOIN privileges p ON mp.privilege_id = p.id
        ORDER BY m.module_name, p.priv_name
    ");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $modules = [];
    foreach ($results as $row) {
        if (!isset($modules[$row['module_id']])) {
            $modules[$row['module_id']] = [
                'id'         => $row['module_id'],
                'name'       => $row['module_name'],
                'privileges' => []
            ];
        }
        // Only push if there's an actual privilege_id
        if ($row['privilege_id']) {
            // Avoid duplicates
            $modules[$row['module_id']]['privileges'][] = [
                'id'   => $row['privilege_id'],
                'name' => $row['priv_name']
            ];
        }
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Fetch all privileges (for Add Module modal)
try {
    $stmt = $pdo->prepare("SELECT id, priv_name FROM privileges ORDER BY priv_name");
    $stmt->execute();
    $allPrivileges = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Modules &amp; Privileges Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .wrapper {
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: 300px;
            background-color: #2c3e50;
            color: #fff;
        }
        .main-content {
            flex: 1;
            padding: 20px;
            margin-left: 300px;
        }
        .privilege-badge {
            margin: 2px;
            display: inline-block;
        }
        /* Toast styling */
        #toastContainer {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9999;
            width: 350px;
        }
        .custom-toast {
            margin-bottom: 10px;
            min-width: 300px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border-radius: 4px;
            display: none;
            position: relative;
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.3s ease, transform 0.3s ease;
        }
        .custom-toast.show {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }
        .toast-success {
            background-color: #d4edda;
            color: #155724;
            border-left: 5px solid #28a745;
        }
        .toast-error {
            background-color: #f8d7da;
            color: #721c24;
            border-left: 5px solid #dc3545;
        }
        .toast-header {
            background-color: rgba(255, 255, 255, 0.85);
            padding: 10px 15px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }
        .toast-body {
            padding: 15px;
            font-size: 16px;
        }
        .toast-close {
            cursor: pointer;
            background: transparent;
            border: 0;
            font-size: 1.25rem;
            font-weight: 700;
            opacity: 0.5;
            padding: 0;
            margin-left: auto;
        }
        .toast-close:hover {
            opacity: 1;
        }
        /* Progress bar for toast timeout */
        .toast-progress {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 3px;
            background-color: rgba(255, 255, 255, 0.7);
            width: 100%;
            transform-origin: left;
        }

        .toast-success .toast-progress {
            background-color: #28a745;
        }

        .toast-error .toast-progress {
            background-color: #dc3545;
        }

        /* Animation for the progress bar */
        @keyframes countdown {
            from { transform: scaleX(1); }
            to { transform: scaleX(0); }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="sidebar">
        <?php include '../../general/sidebar.php'; ?>
    </div>
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Modules &amp; Privileges Management</h1>
                <div>
                    <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#addModuleModal">
                        <i class="bi bi-plus-circle"></i> Add Module
                    </button>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addPrivilegeModal">
                        <i class="bi bi-plus-circle"></i> Add Privilege
                    </button>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                            <tr>
                                <th>Module Name</th>
                                <th>Privileges</th>
                                <th>Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (!empty($modules)): ?>
                                <?php foreach ($modules as $module): ?>
                                    <tr data-module-id="<?= htmlspecialchars($module['id']) ?>">
                                        <td><?= htmlspecialchars($module['name']) ?></td>
                                        <td>
                                            <?php if (!empty($module['privileges'])): ?>
                                                <?php foreach ($module['privileges'] as $privilege): ?>
                                                    <span class="badge bg-info privilege-badge">
                                                        <?= htmlspecialchars($privilege['name']) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <em>None</em>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-warning edit-privileges-btn"
                                                    data-module-id="<?= $module['id'] ?>"
                                                    data-module-name="<?= htmlspecialchars($module['name']) ?>"
                                                    data-bs-toggle="modal" data-bs-target="#editPrivilegesModal">
                                                <i class="bi bi-pencil"></i> Edit
                                            </button>
                                            <button class="btn btn-sm btn-danger delete-module-btn"
                                                    data-module-id="<?= $module['id'] ?>"
                                                    data-module-name="<?= htmlspecialchars($module['name']) ?>"
                                                    data-bs-toggle="modal" data-bs-target="#confirmDeleteModal">
                                                <i class="bi bi-trash"></i> Delete
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3">No modules found.</td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div><!-- /.table-responsive -->
                </div><!-- /.card-body -->
            </div><!-- /.card -->
        </div><!-- /.container-fluid -->
    </div><!-- /.main-content -->
</div><!-- /.wrapper -->

<!-- Toast container - moved outside of wrapper to ensure visibility -->
<div id="toastContainer"></div>

<!-- Add Module Modal -->
<div class="modal fade" id="addModuleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Module</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addModuleForm">
                    <div class="mb-3">
                        <label for="moduleName" class="form-label">Module Name</label>
                        <input type="text" class="form-control" id="moduleName" name="module_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Select Privileges</label>
                        <div class="privilege-checkboxes">
                            <?php // We still show ALL privileges here so a new module can pick any of them ?>
                            <?php foreach ($allPrivileges as $privilege): ?>
                                <div class="form-check">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           name="privileges[]"
                                           value="<?= $privilege['id'] ?>"
                                           id="priv<?= $privilege['id'] ?>">
                                    <label class="form-check-label" for="priv<?= $privilege['id'] ?>">
                                        <?= htmlspecialchars($privilege['priv_name']) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveModuleBtn">Save Module</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Privileges Modal -->
<div class="modal fade" id="editPrivilegesModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Privileges</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editPrivilegesForm">
                    <input type="hidden" id="editModuleId" name="module_id">
                    <div class="mb-3">
                        <label class="form-label">Select Privileges</label>
                        <!-- We'll populate these checkboxes in JS after the AJAX call -->
                        <div id="editPrivilegesCheckboxes" class="privilege-checkboxes"></div>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Confirm Delete Modal -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this module?</p>
                <input type="hidden" id="deleteModuleId" name="deleteModuleId">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
            </div>
        </div>
    </div>
</div>

<!-- jQuery & Bootstrap JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    $(document).ready(function () {

        // -------------------------------
        // 1) ADD MODULE AJAX
        // -------------------------------
        // Custom Toast implementation
        function showToast(message, type = 'success') {
            const toastId = 'toast-' + Date.now();
            const toastClass = type === 'success' ? 'toast-success' : 'toast-error';
            const iconClass = type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill';
            const duration = 3000; // Toast duration in milliseconds

            const toast = `
                <div id="${toastId}" class="custom-toast ${toastClass}">
                    <div class="toast-header">
                        <i class="bi ${iconClass} me-2"></i>
                        <strong class="me-auto">${type === 'success' ? 'Success' : 'Error'}</strong>
                        <button type="button" class="toast-close" onclick="closeToast('${toastId}')">&times;</button>
                    </div>
                    <div class="toast-body">
                        ${message}
                    </div>
                    <div id="progress-${toastId}" class="toast-progress"></div>
                </div>
            `;

            $('#toastContainer').append(toast);

            // Show the toast with a slight delay for better visual effect
            setTimeout(function() {
                const toast = $(`#${toastId}`);
                toast.css('display', 'block');

                // Trigger reflow to ensure transition applies
                toast[0].offsetHeight;

                // Apply visible state
                toast.addClass('show');

                // Start the progress bar animation
                const progressBar = $(`#progress-${toastId}`);
                progressBar.css({
                    'animation': `countdown ${duration/1000}s linear forwards`
                });
            }, 100);

            // Auto-hide the toast after specified duration
            setTimeout(function() {
                closeToast(toastId);
            }, duration);
        }

        // Make closeToast function global so it can be called from the onclick attribute
        window.closeToast = function(toastId) {
            const toast = $(`#${toastId}`);

            // Remove the show class to trigger the CSS transition
            toast.removeClass('show');

            // Remove from DOM after transition completes
            setTimeout(function() {
                toast.remove();
            }, 300); // Match transition duration (300ms)
        };

        // Add Module AJAX call
        $('#saveModuleBtn').on('click', function () {
            let formData = $('#addModuleForm').serialize();
            $.ajax({
                url: 'manage_privileges.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        showToast(response.message, 'success');
                        $('#addModuleModal').modal('hide');
                        setTimeout(function() {
                            location.reload();
                        }, 5000);
                    } else {
                        showToast(response.message, 'error');
                    }
                },
                error: function () {
                    showToast('Error submitting form.', 'error');
                }
            });
        });

        // -------------------------------
        // 2) EDIT PRIVILEGES BUTTON
        //    - fetch assigned privileges
        //    - build checkboxes in the modal
        // -------------------------------
        // 2) EDIT PRIVILEGES BUTTON -> load assigned privileges and build checkboxes accordingly
        // Load current privileges when Edit button is clicked
        $('.edit-privileges-btn').on('click', function () {
            var moduleId = $(this).data('module-id');
            $('#editModuleId').val(moduleId);

            // Clear out any old checkboxes
            $('#editPrivilegesCheckboxes').empty();

            // AJAX to fetch all privileges with an "assigned" flag for this module
            $.ajax({
                url: 'fetch_privileges.php',
                type: 'GET',
                data: { module_id: moduleId },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        // Loop through all privileges
                        response.privileges.forEach(function(priv) {
                            let checked = priv.assigned ? 'checked' : '';
                            let checkboxHtml = `
                        <div class="form-check">
                            <input class="form-check-input"
                                   type="checkbox"
                                   name="privileges[]"
                                   value="${priv.id}"
                                   id="edit_priv_${priv.id}"
                                   ${checked}>
                            <label class="form-check-label" for="edit_priv_${priv.id}">
                                ${priv.priv_name}
                            </label>
                        </div>
                    `;
                            $('#editPrivilegesCheckboxes').append(checkboxHtml);
                        });
                    } else {
                        showToast(response.message, 'error');
                    }
                },
                error: function () {
                    showToast('Error fetching privileges.', 'error');
                }
            });
        });

        // -------------------------------
        // 3) SAVE CHANGES TO PRIVILEGES
        // -------------------------------
        $('#editPrivilegesForm').on('submit', function (event) {
            event.preventDefault();
            let formData = $(this).serialize();
            $.ajax({
                url: 'update_privileges.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        showToast(response.message, 'success');
                        $('#editPrivilegesModal').modal('hide');
                        setTimeout(function() {
                            location.reload();
                        }, 5000);
                    } else {
                        showToast(response.message, 'error');
                    }
                },
                error: function () {
                    showToast('Error updating privileges.', 'error');
                }
            });
        });

        // -------------------------------
        // 4) SET MODULE ID FOR DELETION
        // -------------------------------
        $('.delete-module-btn').on('click', function () {
            var moduleId = $(this).data('module-id');
            $('#deleteModuleId').val(moduleId);
        });

        // -------------------------------
        // 5) DELETE MODULE AJAX
        // -------------------------------
        $('#confirmDeleteBtn').on('click', function () {
            var moduleId = $('#deleteModuleId').val();
            $.ajax({
                url: 'delete_module.php',
                type: 'POST',
                data: { module_id: moduleId },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        showToast(response.message, 'success');
                        $('#confirmDeleteModal').modal('hide');
                        setTimeout(function() {
                            location.reload();
                        }, 5000);
                    } else {
                        showToast(response.message, 'error');
                    }
                },
                error: function () {
                    showToast('Error deleting module.', 'error');
                }
            });
        });
    });
</script>
</body>
</html>