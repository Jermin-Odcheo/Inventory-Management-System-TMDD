<?php
session_start();
include $_SERVER['DOCUMENT_ROOT'] . '/IMS-TMDD RABAC Tester/config/ims-tmdd.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Fetch users and their roles
$query = "
    SELECT u.id, u.username, GROUP_CONCAT(DISTINCT r.role_name) AS roles, 
           GROUP_CONCAT(DISTINCT d.department_name) AS departments 
    FROM users u
    LEFT JOIN user_roles ur ON u.id = ur.user_id
    LEFT JOIN roles r ON ur.role_id = r.id
    LEFT JOIN user_departments ud ON u.id = ud.user_id
    LEFT JOIN departments d ON ud.department_id = d.id
    GROUP BY u.id
";

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../style/style.css">
</head>

<body>

    <!-- Navbar -->
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">User Management</a>
            <a href="../../../../config/logout.php" class="btn btn-danger">Logout</a>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 bg-light p-3">
                <?php include $_SERVER['DOCUMENT_ROOT'] . '/IMS-TMDD RABAC Tester/src/view/php/general/sidebar.php'; ?>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 p-4">
                <h2>User Management</h2>
                <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addUserModal">Add User</button>

                <table class="table table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Roles</th>
                            <th>Departments</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['id']); ?></td>
                                <td><?php echo htmlspecialchars($row['username']); ?></td>
                                <td><?php echo htmlspecialchars($row['roles'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($row['departments'] ?? 'N/A'); ?></td>
                                <td>
                                    <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editUserModal"
                                        data-id="<?php echo $row['id']; ?>"
                                        data-username="<?php echo htmlspecialchars($row['username']); ?>"
                                        data-roles="<?php echo htmlspecialchars($row['roles']); ?>"
                                        data-departments="<?php echo htmlspecialchars($row['departments']); ?>">
                                        Edit
                                    </button>
                                    <button class="btn btn-danger btn-sm">Delete</button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addUserModalLabel">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="add_user.php" method="POST">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="roles" class="form-label">Roles</label>
                            <input type="text" class="form-control" id="roles" name="roles" required>
                        </div>
                        <div class="mb-3">
                            <label for="departments" class="form-label">Departments</label>
                            <input type="text" class="form-control" id="departments" name="departments" required>
                        </div>
                        <button type="submit" class="btn btn-success">Add User</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="edit_user.php" method="POST">
                        <input type="hidden" id="edit-user-id" name="user_id">
                        <div class="mb-3">
                            <label for="edit-username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="edit-username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit-roles" class="form-label">Roles</label>
                            <input type="text" class="form-control" id="edit-roles" name="roles" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit-departments" class="form-label">Departments</label>
                            <input type="text" class="form-control" id="edit-departments" name="departments" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Update User</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Populate Edit Modal with User Data
        document.addEventListener("DOMContentLoaded", function() {
            var editUserModal = document.getElementById("editUserModal");
            editUserModal.addEventListener("show.bs.modal", function(event) {
                var button = event.relatedTarget;
                var userId = button.getAttribute("data-id");
                var username = button.getAttribute("data-username");
                var roles = button.getAttribute("data-roles");
                var departments = button.getAttribute("data-departments");

                document.getElementById("edit-user-id").value = userId;
                document.getElementById("edit-username").value = username;
                document.getElementById("edit-roles").value = roles;
                document.getElementById("edit-departments").value = departments;
            });
        });
    </script>

</body>

</html>