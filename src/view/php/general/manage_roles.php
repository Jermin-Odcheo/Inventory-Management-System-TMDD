<?php
session_start();
require_once('../../../../config/ims-tmdd.php');

// Optional: Check if the logged-in user has permission to manage roles.
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// SQL to retrieve roles with their modules and privileges.
$sql = "
    SELECT 
        r.Role_ID,
        r.Role_Name,
        COALESCE(m.Module_Name, 'General') AS Module_Name,
        p.Privilege_Name
    FROM roles r
    LEFT JOIN role_privileges rp ON r.Role_ID = rp.Role_ID
    LEFT JOIN privileges p ON rp.Privilege_ID = p.Privilege_ID
    LEFT JOIN modules m ON p.Module_ID = m.Module_ID
    ORDER BY r.Role_ID ASC, m.Module_Name, p.Privilege_Name
";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$roleData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group data by role and module.
$roles = [];
foreach ($roleData as $row) {
    $roleID = $row['Role_ID'];
    if (!isset($roles[$roleID])) {
        $roles[$roleID] = [
            'Role_Name' => $row['Role_Name'],
            'Modules'   => []
        ];
    }
    $moduleName = $row['Module_Name'];
    if (!isset($roles[$roleID]['Modules'][$moduleName])) {
        $roles[$roleID]['Modules'][$moduleName] = [];
    }
    if ($row['Privilege_Name'] !== null) {
        $roles[$roleID]['Modules'][$moduleName][] = $row['Privilege_Name'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Roles</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap CSS (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom Admin CSS (sidebar) -->
    <link rel="stylesheet" href="../../styles/css/sidebar.css">
    <style>
        /* Unified blue & black theme */
        body {
            background-color: #181818;
            color: #e0e0e0;
            font-family: 'Arial', sans-serif;
        }
        h1 {
            color: #0d6efd;
            text-align: center;
            margin-top: 20px;
            font-size: 28px;
        }
        /* Main content area styled consistently with user_management.php */
        .content {
            margin-left: 260px;
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
        .btn-info {
            background-color: #17a2b8;
            border-color: #17a2b8;
        }
        .table td,
        .table th {
            color: #ffffff !important;
        }

    </style>
</head>
<body>
    <!-- Include Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3">Role Management</h1>
                <a href="add_role.php" class="btn btn-primary">Add New Role</a>
            </div>
 
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead>
                                <tr>
                                    <th scope="col">Role ID</th>
                                    <th scope="col">Role Name</th>
                                    <th scope="col">Modules &amp; Privileges</th>
                                    <th scope="col">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($roles)): ?>
                                    <?php foreach ($roles as $roleID => $role): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($roleID); ?></td>
                                            <td><?php echo htmlspecialchars($role['Role_Name']); ?></td>
                                            <td>
                                                <?php if (!empty($role['Modules'])): ?>
                                                    <ul class="list-unstyled mb-0">
                                                        <?php foreach ($role['Modules'] as $moduleName => $privileges): ?>
                                                            <li>
                                                                <strong><?php echo htmlspecialchars($moduleName); ?></strong>:
                                                                <?php 
                                                                    echo !empty($privileges)
                                                                        ? implode(', ', array_map('htmlspecialchars', $privileges))
                                                                        : '<em>No privileges</em>';
                                                                ?>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                <?php else: ?>
                                                    <em>No modules assigned</em>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="edit_roles.php?id=<?php echo $roleID; ?>" class="btn btn-sm btn-warning mb-1">Edit</a>
                                                <a href="delete_role.php?id=<?php echo $roleID; ?>" class="btn btn-sm btn-danger mb-1" onclick="return confirm('Are you sure you want to delete this role?');">Delete</a>
                                                <a href="assign_privileges.php?id=<?php echo $roleID; ?>" class="btn btn-sm btn-info mb-1">Assign Privileges</a>
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
                    </div><!-- /.table-responsive -->
                </div><!-- /.card-body -->
 
        </div><!-- /.container-fluid -->
    </div><!-- /.content -->

    <!-- Bootstrap Bundle with Popper (CDN) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
