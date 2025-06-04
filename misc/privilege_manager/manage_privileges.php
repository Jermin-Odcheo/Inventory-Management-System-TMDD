<?php
/**
 * @file manage_privileges.php
 * @brief Manages the display, creation, editing, and deletion of privileges.
 *
 * This script provides a user interface for administrators to manage system privileges.
 * It integrates with an RBAC (Role-Based Access Control) service to enforce permissions
 * for viewing, creating, modifying, and removing privileges. It fetches existing privileges
 * from the database and displays them in a table, along with modals for CRUD operations.
 * Client-side JavaScript handles the modal interactions and form submissions (simulated in this version).
 */

require_once('../../../../../../config/ims-tmdd.php'); // Include the database connection file, providing the $pdo object.
require_once('../../../../../control/RBACService.php'); // Include the RBACService class.
session_start(); // Start the PHP session.

include '../../../general/header.php'; // Include the general header HTML.
include '../../../general/sidebar.php'; // Include the general sidebar HTML.
include '../../../general/footer.php'; // Include the general footer HTML.

/**
 * @var RBACService $rbac Initializes the RBACService with the PDO object and current user ID.
 * Enforces 'View' privilege for 'Roles and Privileges' to access this page.
 */
$rbac = new RBACService($pdo, $_SESSION['user_id']);
$rbac->requirePrivilege('Roles and Privileges', 'View');

/**
 * @var bool $canCreate Flag indicating if the user has 'Create' privilege for 'Roles and Privileges'.
 * @var bool $canModify Flag indicating if the user has 'Modify' privilege for 'Roles and Privileges'.
 * @var bool $canRemove Flag indicating if the user has 'Remove' privilege for 'Roles and Privileges'.
 *
 * These flags are used to conditionally display/enable buttons and features on the page.
 */
$canCreate = $rbac->hasPrivilege('Roles and Privileges', 'Create');
$canModify = $rbac->hasPrivilege('Roles and Privileges', 'Modify');
$canRemove = $rbac->hasPrivilege('Roles and Privileges', 'Remove');

/**
 * Fetches all privilege names and their IDs from the `privileges` table, ordered by name.
 *
 * @var PDOStatement $stmt The PDOStatement object resulting from the query.
 * @var array $privileges An associative array containing all fetched privileges.
 */
$stmt = $pdo->query("SELECT id, priv_name FROM privileges ORDER BY priv_name");
$privileges = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>src/view/styles/css/pagination.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>src/view/styles/css/manage_privileges.css">
    <title>Manage Users</title>
</head>

<body>
    <div class="main-content">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Privilege Management</h5>
                <?php if ($canCreate): ?>
                    <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#createPrivilegeModal">
                        ➕ Add Privilege
                    </button>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Privilege Name</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($privileges as $index => $priv): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= htmlspecialchars($priv['priv_name']) ?></td>
                                    <td>
                                        <?php if ($canModify): ?>
                                            <button class="btn btn-sm btn-warning me-1 edit-btn"
                                                data-id="<?= $priv['id'] ?>"
                                                data-name="<?= htmlspecialchars($priv['priv_name']) ?>"
                                                data-bs-toggle="modal" data-bs-target="#editPrivilegeModal">
                                                ✏️ Edit
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($canRemove): ?>
                                            <button class="btn btn-sm btn-danger delete-btn"
                                                data-id="<?= $priv['id'] ?>"
                                                data-bs-toggle="modal" data-bs-target="#deletePrivilegeModal">
                                                🗑️ Delete
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Create Modal -->
        <div class="modal fade" id="createPrivilegeModal" tabindex="-1" data-bs-backdrop="true" data-bs-keyboard="false">
            <div class="modal-dialog modal-dialog-centered">
                <form id="createPrivilegeForm" class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">Add Privilege</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <label for="priv_name" class="form-label">Privilege Name</label>
                        <input type="text" class="form-control" id="priv_name" name="priv_name" required>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Create</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Modal -->
        <div class="modal fade" id="editPrivilegeModal" tabindex="-1" data-bs-backdrop="true" data-bs-keyboard="false">
            <div class="modal-dialog modal-dialog-centered">
                <form id="editPrivilegeForm" class="modal-content">
                    <div class="modal-header bg-warning">
                        <h5 class="modal-title">Edit Privilege</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="editPrivilegeId" name="id">
                        <label for="editPrivilegeName" class="form-label">Privilege Name</label>
                        <input type="text" class="form-control" id="editPrivilegeName" name="priv_name" required>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-warning">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Delete Modal -->
        <div class="modal fade" id="deletePrivilegeModal" tabindex="-1" data-bs-backdrop="true" data-bs-keyboard="false">
            <div class="modal-dialog modal-dialog-centered">
                <form id="deletePrivilegeForm" class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title">Delete Privilege</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="deletePrivilegeId" name="id">
                        <p>Are you sure you want to delete this privilege?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-danger">Yes, Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        /**
         * @function closeModal
         * @brief Hides a Bootstrap modal and removes its backdrop.
         * @param {string} modalId The ID of the modal element to close.
         */
        document.addEventListener('DOMContentLoaded', () => {
            function closeModal(modalId) {
                const modalElement = document.getElementById(modalId);
                // Get the Bootstrap modal instance.
                const modalInstance = bootstrap.Modal.getInstance(modalElement);
                if (modalInstance) modalInstance.hide(); // Hide the modal if an instance exists.

                // Remove lingering backdrop element that Bootstrap might leave behind.
                const backdrop = document.querySelector('.modal-backdrop');
                if (backdrop) backdrop.remove();
            }

            // Simulate "Add Privilege"
            /**
             * @event submit
             * @memberof createPrivilegeForm
             * @brief Event listener for the submission of the "Add Privilege" form.
             * This is a simulated client-side submission; in a real application, this would involve an AJAX call.
             * Displays an alert and closes the modal.
             */
            document.getElementById('createPrivilegeForm').addEventListener('submit', e => {
                e.preventDefault(); // Prevent default form submission.
                alert('Privilege created (simulated).'); // Simulated success message.
                closeModal('createPrivilegeModal'); // Close the modal.
            });

            // Fill Edit Modal with clicked data
            /**
             * @event click
             * @memberof .edit-btn
             * @brief Event listener for clicks on "Edit" buttons.
             * Populates the "Edit Privilege" modal's form fields with the data of the clicked privilege
             * using `data-id` and `data-name` attributes.
             */
            document.querySelectorAll('.edit-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    document.getElementById('editPrivilegeId').value = btn.dataset.id; // Set hidden ID field.
                    document.getElementById('editPrivilegeName').value = btn.dataset.name; // Set privilege name field.
                });
            });

            // Simulate "Edit Privilege"
            /**
             * @event submit
             * @memberof editPrivilegeForm
             * @brief Event listener for the submission of the "Edit Privilege" form.
             * This is a simulated client-side submission; in a real application, this would involve an AJAX call.
             * Displays an alert and closes the modal.
             */
            document.getElementById('editPrivilegeForm').addEventListener('submit', e => {
                e.preventDefault(); // Prevent default form submission.
                alert('Privilege edited (simulated).'); // Simulated success message.
                closeModal('editPrivilegeModal'); // Close the modal.
            });

            // Fill Delete Modal with clicked data
            /**
             * @event click
             * @memberof .delete-btn
             * @brief Event listener for clicks on "Delete" buttons.
             * Populates the "Delete Privilege" modal's hidden ID field with the ID of the privilege
             * to be deleted using the `data-id` attribute.
             */
            document.querySelectorAll('.delete-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    document.getElementById('deletePrivilegeId').value = btn.dataset.id; // Set hidden ID field.
                });
            });

            // Simulate "Delete Privilege"
            /**
             * @event submit
             * @memberof deletePrivilegeForm
             * @brief Event listener for the submission of the "Delete Privilege" form.
             * This is a simulated client-side submission; in a real application, this would involve an AJAX call.
             * Displays an alert and closes the modal.
             */
            document.getElementById('deletePrivilegeForm').addEventListener('submit', e => {
                e.preventDefault(); // Prevent default form submission.
                alert('Privilege deleted (simulated).'); // Simulated success message.
                closeModal('deletePrivilegeModal'); // Close the modal.
            });
        });
    </script>

</body>

</html>
