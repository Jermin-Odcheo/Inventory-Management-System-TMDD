<?php
session_start();
require_once('../../../../config/ims-tmdd.php');

// Check if the logged-in user is allowed to manage users.
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Fetch all users from the database.
$stmt = $pdo->prepare("SELECT * FROM users");
$stmt->execute();
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users</title>
    <!-- Sidebar and custom user management CSS -->
    <link rel="stylesheet" href="../../../styles/css/sidebar.css">
    <link rel="stylesheet" href="../../../styles/css/user_management.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        /* Unified blue & black theme */
        body {
            background-color: #181818;
            color: #ffffff; /* Lighter text */
            font-family: 'Arial', sans-serif;
        }
        h1 {
            color: #66b2ff; /* Lighter blue for headings */
            text-align: center;
            margin-top: 20px;
            font-size: 28px;
        }
        /* Main content area styled with a dark background and blue accents */
        .main-content {
            margin-left: 260px; /* Adjust according to your sidebar width */
            padding: 20px;
            background-color: #242424;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.5);
            margin-bottom: 20px;
        }
        /* Table styling */
        .table-responsive table {
            background-color: #242424;
        }
        .table thead th {
            background-color: #0d6efd;
            color: #fff;
        }
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: #2a2a2a;
        }
        .table-hover tbody tr:hover {
            background-color: #343a40;
        }
        /* Button overrides */
        .btn-primary {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        .btn-warning {
            background-color: #ffc107;
            border-color: #ffc107;
            color: #000;
        }
        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
        }
        .table td,
        .table th {
            color: #ffffff !important;
        }

    </style>
</head>
<body>
    <!-- Sidebar (styled via sidebar.css) -->
    <div class="sidebar">
        <?php include 'sidebar.php'; ?>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        <h1>User Management</h1>
        <div class="d-flex justify-content-end mb-3">
            <a href="add_user.php" class="btn btn-primary">Add New User</a>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Email</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>Roles</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <?php
                        // Fetch roles for each user.
                        $stmtRole = $pdo->prepare("SELECT r.Role_Name 
                                                   FROM roles r 
                                                   JOIN user_roles ur ON r.Role_ID = ur.Role_ID 
                                                   WHERE ur.User_ID = ?");
                        $stmtRole->execute([$user['User_ID']]);
                        $roles = $stmtRole->fetchAll(PDO::FETCH_COLUMN);
                        ?>
                        <tr>
                            <td><?php echo $user['User_ID']; ?></td>
                            <td><?php echo htmlspecialchars($user['Email']); ?></td>
                            <td><?php echo htmlspecialchars($user['First_Name'] . ' ' . $user['Last_Name']); ?></td>
                            <td><?php echo htmlspecialchars($user['Department']); ?></td>
                            <td><?php echo htmlspecialchars($user['Status']); ?></td>
                            <td><?php echo implode(', ', $roles); ?></td>
                            <td>
                                <a href="edit_user.php?id=<?php echo $user['User_ID']; ?>" class="btn btn-sm btn-warning">Edit</a>
                                <a href="delete_user.php?id=<?php echo $user['User_ID']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div><!-- /.table-responsive -->
    </div><!-- /.main-content -->

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
