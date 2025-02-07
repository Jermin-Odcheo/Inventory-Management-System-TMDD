<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if database connection is established
if (!isset($pdo)) {
    die("Database connection is not established.");
}

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Define allowed sorting columns
$allowedSortColumns = ['User_ID', 'Email', 'First_Name', 'Last_Name', 'Department', 'Status', 'is_deleted'];

// Default sort column
$sortBy = isset($_GET['sort']) && in_array($_GET['sort'], $allowedSortColumns) ? $_GET['sort'] : 'User_ID';

// Default sort direction
$sortDir = isset($_GET['dir']) && $_GET['dir'] == 'desc' ? 'desc' : 'asc';

// Check if 'tab' is set to switch between active and deleted users
$isDeletedTab = isset($_GET['tab']) && $_GET['tab'] == 'deleted';

// Modify the query based on the tab (active or deleted users)
$query = $isDeletedTab ? "SELECT * FROM users WHERE is_deleted = 1 ORDER BY `$sortBy` $sortDir" : "SELECT * FROM users WHERE is_deleted = 0 ORDER BY `$sortBy` $sortDir";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$users) {
        $users = [];
    }
} catch (PDOException $e) {
    die("Database query error: " . $e->getMessage());
}

// Function to toggle sort direction for a column.
function toggleDirection($currentSort, $currentDir, $column)
{
    return $currentSort === $column ? ($currentDir === 'asc' ? 'desc' : 'asc') : 'asc';
}

// Function to display a sort icon if the column is the one being sorted.
function sortIcon($currentSort, $column, $sortDir)
{
    if ($currentSort === $column) {
        return $sortDir === 'asc'
            ? ' <i class="bi bi-caret-up-fill"></i>'
            : ' <i class="bi bi-caret-down-fill"></i>';
    }
    return '';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Manage Users</title>
    <link rel="stylesheet" href="../styles/user_management.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<style>
    .main-content {
        margin-left: 300px;
        /* Adjust according to your sidebar width */
        padding: 20px;
        margin-bottom: 20px;
        width: auto;
    }
</style>

<body>

    <div class="sidebar">
        <?php include '../../general/sidebar.php'; ?>
    </div>

    <!-- Main Content Area -->
    <div class="main-content container-fluid">
        <h1>User Management</h1>
        <div class="d-flex justify-content-end mb-3">
            <a href="/src//view/php/modules/user_manager/add_user.php" class="btn btn-primary">Add New User</a>
        </div>

        <!-- Bootstrap Nav Tabs to toggle between Active and Deleted Users -->
        <ul class="nav nav-tabs" id="userTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo !$isDeletedTab ? 'active' : ''; ?>" href="?tab=active&sort=<?php echo $sortBy; ?>&dir=<?php echo $sortDir; ?>">
                    Active Users
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo $isDeletedTab ? 'active' : ''; ?>" href="?tab=deleted&sort=<?php echo $sortBy; ?>&dir=<?php echo $sortDir; ?>">
                    Deleted Users
                </a>
            </li>
        </ul>

        <div class="tab-content" id="userTabsContent">
            <!-- Active Users Tab -->
            <div class="tab-pane fade <?php echo !$isDeletedTab ? 'show active' : ''; ?>" id="active-users" role="tabpanel" aria-labelledby="active-tab">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>
                                    <a class="text-white text-decoration-none" href="?tab=active&sort=User_ID&dir=<?php echo toggleDirection($sortBy, $sortDir, 'User_ID'); ?>">
                                        User ID<?php echo sortIcon($sortBy, 'User_ID', $sortDir); ?>
                                    </a>
                                </th>
                                <th>
                                    <a class="text-white text-decoration-none" href="?tab=active&sort=Email&dir=<?php echo toggleDirection($sortBy, $sortDir, 'Email'); ?>">
                                        Email<?php echo sortIcon($sortBy, 'Email', $sortDir); ?>
                                    </a>
                                </th>
                                <th>
                                    <a class="text-white text-decoration-none" href="?tab=active&sort=First_Name&dir=<?php echo toggleDirection($sortBy, $sortDir, 'First_Name'); ?>">
                                        Name<?php echo sortIcon($sortBy, 'First_Name', $sortDir); ?>
                                    </a>
                                </th>
                                <th>
                                    <a class="text-white text-decoration-none" href="?tab=active&sort=Department&dir=<?php echo toggleDirection($sortBy, $sortDir, 'Department'); ?>">
                                        Department<?php echo sortIcon($sortBy, 'Department', $sortDir); ?>
                                    </a>
                                </th>
                                <th>
                                    <a class="text-white text-decoration-none" href="?tab=active&sort=Status&dir=<?php echo toggleDirection($sortBy, $sortDir, 'Status'); ?>">
                                        Status<?php echo sortIcon($sortBy, 'Status', $sortDir); ?>
                                    </a>
                                </th>
                                <th>
                                    <a class="text-white text-decoration-none" href="?tab=active&sort=Roles&dir=<?php echo toggleDirection($sortBy, $sortDir, 'Roles'); ?>">
                                        Roles<?php echo sortIcon($sortBy, 'Roles', $sortDir); ?>
                                    </a>
                                </th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['User_ID']); ?></td>
                                    <td><?php echo htmlspecialchars($user['Email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['First_Name'] . ' ' . $user['Last_Name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['Department']); ?></td>
                                    <td><?php echo htmlspecialchars($user['Status']); ?></td>
                                    <td>
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
                                        echo implode(', ', $roles);
                                        ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-warning btn-edit"
                                            data-id="<?php echo $user['User_ID']; ?>"
                                            data-email="<?php echo htmlspecialchars($user['Email']); ?>"
                                            data-first-name="<?php echo htmlspecialchars($user['First_Name']); ?>"
                                            data-last-name="<?php echo htmlspecialchars($user['Last_Name']); ?>"
                                            data-department="<?php echo htmlspecialchars($user['Department']); ?>"
                                            data-status="<?php echo htmlspecialchars($user['Status']); ?>">
                                            Edit
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="openDeleteModal(<?php echo $user['User_ID']; ?>)">
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div><!-- /.table-responsive -->
            </div>

            <!-- Deleted Users Tab -->
            <div class="tab-pane fade <?php echo $isDeletedTab ? 'show active' : ''; ?>" id="deleted-users" role="tabpanel" aria-labelledby="deleted-tab">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
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
                                <tr>
                                    <td><?php echo htmlspecialchars($user['User_ID']); ?></td>
                                    <td><?php echo htmlspecialchars($user['Email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['First_Name'] . ' ' . $user['Last_Name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['Department']); ?></td>
                                    <td><?php echo htmlspecialchars($user['Status']); ?></td>
                                    <td>
                                        <?php
                                        // Fetch roles for each user
                                        $stmtRole = $pdo->prepare("
                                            SELECT r.Role_Name
                                            FROM roles r 
                                            JOIN user_roles ur ON r.Role_ID = ur.Role_ID 
                                            WHERE ur.User_ID = ?
                                        ");
                                        $stmtRole->execute([$user['User_ID']]);
                                        $roles = $stmtRole->fetchAll(PDO::FETCH_COLUMN);
                                        echo implode(', ', $roles);
                                        ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-warning btn-edit"
                                            data-id="<?php echo $user['User_ID']; ?>"
                                            data-email="<?php echo htmlspecialchars($user['Email']); ?>"
                                            data-first-name="<?php echo htmlspecialchars($user['First_Name']); ?>"
                                            data-last-name="<?php echo htmlspecialchars($user['Last_Name']); ?>"
                                            data-department="<?php echo htmlspecialchars($user['Department']); ?>"
                                            data-status="<?php echo htmlspecialchars($user['Status']); ?>">
                                            Edit
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="openDeleteModal(<?php echo $user['User_ID']; ?>)">
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div><!-- /.table-responsive -->
            </div>
        </div><!-- /.tab-content -->
    </div><!-- /.main-content -->

    <!-- Include Edit Modal -->
    <?php include 'edit_user.php'; ?>

    <script>
    $(document).ready(function() {
        // Click event for Edit button
        $(".btn-edit").click(function() {
            let userData = {
                ID: $(this).data("id"),
                Email: $(this).data("email"),
                First_Name: $(this).data("firstname"),
                Last_Name: $(this).data("lastname"),
                Department: $(this).data("department"),
                Status: $(this).data("status")
            };

            showEditModal(userData);
        });

        window.showEditModal = function(data) {
            $("#editID").val(data.ID);

            let fields = `
                <label>Email:</label><input type='email' class='form-control' name='Email' value='${data.Email}' required>
                <label>First Name:</label><input type='text' class='form-control' name='First_Name' value='${data.First_Name}' required>
                <label>Last Name:</label><input type='text' class='form-control' name='Last_Name' value='${data.Last_Name}' required>
                <label>Department:</label><input type='text' class='form-control' name='Department' value='${data.Department}' required>
                <label>Status:</label>
                <select class='form-control' name='Status'>
                    <option value='Active' ${data.Status === 'Active' ? 'selected' : ''}>Active</option>
                    <option value='Inactive' ${data.Status === 'Inactive' ? 'selected' : ''}>Inactive</option>
                </select>
                <label>New Password (Leave blank to keep current password):</label>
                <input type='password' class='form-control' name='Password'>
            `;

            $("#dynamicFields").html(fields);

            new bootstrap.Modal(document.getElementById("editModal")).show();
        };

        $("#saveChanges").click(function(event) {
            event.preventDefault();

            let formData = new FormData($("#editForm")[0]);

            $.ajax({
                url: "update_user.php",
                type: "POST",
                data: formData,
                contentType: false,
                processData: false,
                success: function(response) {
                    alert(response);
                    location.reload();
                },
                error: function() {
                    alert("Failed to update user.");
                }
            });
        });
    });
    </script>

</body>

</html>
