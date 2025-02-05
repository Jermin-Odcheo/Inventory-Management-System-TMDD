<?php
session_start();
require_once('../../../../config/ims-tmdd.php');

// 1. Check if the logged-in user is allowed to manage users.
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 2. Define which columns are allowed for sorting.
$allowedSortColumns = [
    'User_ID',
    'Email',
    'First_Name',
    'Last_Name',
    'Department',
    'Status',
    'is_deleted'
];

// 3. Get sorting params from the query string, with safe defaults.
$sortBy = isset($_GET['sort']) && in_array($_GET['sort'], $allowedSortColumns)
    ? $_GET['sort']
    : 'User_ID';

$sortDir = isset($_GET['dir']) && in_array($_GET['dir'], ['asc', 'desc'])
    ? $_GET['dir']
    : 'asc';

// 4. Build and execute the SQL query with ORDER BY.
$query = "SELECT * FROM users ORDER BY `$sortBy` $sortDir";
$stmt = $pdo->prepare($query);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 5. A small helper to flip asc/desc for each column link.
function toggleDirection($currentSort, $currentDir, $column)
{
    if ($currentSort === $column) {
        // Toggle asc <-> desc if user repeatedly clicks the same column
        return $currentDir === 'asc' ? 'desc' : 'asc';
    }
    // Default to ascending if switching to a new column
    return 'asc';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Manage Users</title>
    <link rel="stylesheet" href="../../styles/css/user_management.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        /* Unified blue & black theme */
        body {
            background-color: #181818;
            color: #ffffff;
            /* Lighter text */
            font-family: 'Arial', sans-serif;
        }

        h1 {
            color: #66b2ff;
            /* Lighter blue for headings */
            text-align: center;
            margin-top: 20px;
            font-size: 28px;
        }

        /* Main content area styled with a dark background and blue accents */
        .main-content {
            margin-left: 260px;
            /* Adjust according to your sidebar width */
            padding: 20px;
            background-color: #242424;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
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

        a.sort-link {
            color: #ffffff;
            text-decoration: none;
        }

        a.sort-link:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <!-- Sidebar (styled via sidebar.css) -->
    <div class="sidebar">
        <?php include '../general/sidebar.php'; ?>
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
                        <!-- Each column header is clickable for sorting.
                             We use toggleDirection() to figure out the next direction. -->
                        <th>
                            <a class="sort-link"
                                href="?sort=User_ID&dir=<?php echo toggleDirection($sortBy, $sortDir, 'User_ID'); ?>">
                                User ID
                            </a>
                        </th>
                        <th>
                            <a class="sort-link"
                                href="?sort=Email&dir=<?php echo toggleDirection($sortBy, $sortDir, 'Email'); ?>">
                                Email
                            </a>
                        </th>
                        <th>
                            <a class="sort-link"
                                href="?sort=First_Name&dir=<?php echo toggleDirection($sortBy, $sortDir, 'First_Name'); ?>">
                                Name
                            </a>
                        </th>
                        <th>
                            <a class="sort-link"
                                href="?sort=Department&dir=<?php echo toggleDirection($sortBy, $sortDir, 'Department'); ?>">
                                Department
                            </a>
                        </th>
                        <th>
                            <a class="sort-link"
                                href="?sort=Status&dir=<?php echo toggleDirection($sortBy, $sortDir, 'Status'); ?>">
                                Status
                            </a>
                        </th>
                        <th>Roles</th>
                        <th>
                            <a class="sort-link"
                                href="?sort=is_deleted&dir=<?php echo toggleDirection($sortBy, $sortDir, 'is_deleted'); ?>">
                                Deleted?
                            </a>
                        </th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <?php
                        // Fetch roles for each user.
                        $stmtRole = $pdo->prepare("
                            SELECT r.Role_Name
                            FROM roles r 
                            JOIN user_roles ur ON r.Role_ID = ur.Role_ID 
                            WHERE ur.User_ID = ?
                        ");
                        $stmtRole->execute([$user['User_ID']]);
                        $roles = $stmtRole->fetchAll(PDO::FETCH_COLUMN);

                        // Determine if user is deleted:
                        // $user['is_deleted'] is typically '0' or '1' (string) from MySQL.
                        $isDeleted = ($user['is_deleted'] == 1) ? 'Yes' : 'No';
                        ?>
                        <tr>
                            <td><?php echo $user['User_ID']; ?></td>
                            <td><?php echo htmlspecialchars($user['Email']); ?></td>
                            <td><?php echo htmlspecialchars($user['First_Name'] . ' ' . $user['Last_Name']); ?></td>
                            <td><?php echo htmlspecialchars($user['Department']); ?></td>
                            <td><?php echo htmlspecialchars($user['Status']); ?></td>
                            <td><?php echo implode(', ', $roles); ?></td>
                            <td><?php echo $isDeleted; ?></td>
                            <td>
                                <a href="edit_user.php?id=<?php echo $user['User_ID']; ?>"
                                    class="btn btn-sm btn-warning">
                                    Edit
                                </a>
                                <a href="delete_user.php?id=<?php echo $user['User_ID']; ?>"
                                    class="btn btn-sm btn-danger"
                                    onclick="return confirm('Are you sure?');">
                                    Delete
                                </a>
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