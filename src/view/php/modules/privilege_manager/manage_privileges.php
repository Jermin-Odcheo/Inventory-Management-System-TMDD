<?php
require_once('../../../../../config/ims-tmdd.php');
require_once('../../../../control/RBACService.php');
session_start();

include '../../general/header.php';
include '../../general/sidebar.php';
include '../../general/footer.php';

$rbac = new RBACService($pdo, $_SESSION['user_id']);
$rbac->requirePrivilege('Roles and Privileges', 'View');

$canCreate = $rbac->hasPrivilege('Roles and Privileges', 'Create');
$canModify = $rbac->hasPrivilege('Roles and Privileges', 'Modify');
$canRemove = $rbac->hasPrivilege('Roles and Privileges', 'Remove');

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
    <div class="main-content container-fluid">
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
                    <table class="table table-striped table-hover align-middle">
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

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js"></script>

        <script>
            document.addEventListener('DOMContentLoaded', () => {
                document.getElementById('createPrivilegeForm').addEventListener('submit', e => {
                    e.preventDefault();
                    fetch('create_privilege.php', {
                        method: 'POST',
                        body: new FormData(e.target)
                    }).then(res => res.json()).then(data => {
                        if (data.success) location.reload();
                        else alert(data.message);
                    });
                });

                document.querySelectorAll('.edit-btn').forEach(btn => {
                    btn.addEventListener('click', () => {
                        document.getElementById('editPrivilegeId').value = btn.dataset.id;
                        document.getElementById('editPrivilegeName').value = btn.dataset.name;
                    });
                });

                document.getElementById('editPrivilegeForm').addEventListener('submit', e => {
                    e.preventDefault();
                    fetch('edit_privilege.php', {
                        method: 'POST',
                        body: new FormData(e.target)
                    }).then(res => res.json()).then(data => {
                        if (data.success) location.reload();
                        else alert(data.message);
                    });
                });

                document.querySelectorAll('.delete-btn').forEach(btn => {
                    btn.addEventListener('click', () => {
                        document.getElementById('deletePrivilegeId').value = btn.dataset.id;
                    });
                });

                document.getElementById('deletePrivilegeForm').addEventListener('submit', e => {
                    e.preventDefault();
                    fetch('delete_privilege.php', {
                        method: 'POST',
                        body: new FormData(e.target)
                    }).then(res => res.json()).then(data => {
                        if (data.success) location.reload();
                        else alert(data.message);
                    });
                });
            });
        </script>
</body>

</html>
