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
$sortBy = isset($_GET['sort']) && in_array($_GET['sort'], $allowedSortColumns) ? $_GET['sort'] : 'User_ID';
$sortDir = isset($_GET['dir']) && in_array($_GET['dir'], ['asc', 'desc']) ? $_GET['dir'] : 'asc';

try {
    $query = "SELECT * FROM users ORDER BY `$sortBy` $sortDir";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$users) {
        $users = [];
    }
} catch (PDOException $e) {
    die("Database query error: " . $e->getMessage());
}

function toggleDirection($currentSort, $currentDir, $column)
{
    return $currentSort === $column ? ($currentDir === 'asc' ? 'desc' : 'asc') : 'asc';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users</title>
    <link rel="stylesheet" href="../styles/user_management.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>

<div class="sidebar">
    <?php include '../../general/sidebar.php'; ?>
</div>

<div class="main-content">
    <h1>User Management</h1>

    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>First Name</th>
                    <th>Last Name</th>
                    <th>Email</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['First_Name']); ?></td>
                        <td><?php echo htmlspecialchars($user['Last_Name']); ?></td>
                        <td><?php echo htmlspecialchars($user['Email']); ?></td>
                        <td>
                            <button class="btn btn-warning btn-edit"
                                    data-id="<?php echo $user['User_ID']; ?>"
                                    data-email="<?php echo htmlspecialchars($user['Email']); ?>"
                                    data-first-name="<?php echo htmlspecialchars($user['First_Name']); ?>"
                                    data-last-name="<?php echo htmlspecialchars($user['Last_Name']); ?>"
                                    data-department="<?php echo htmlspecialchars($user['Department']); ?>"
                                    data-status="<?php echo htmlspecialchars($user['Status']); ?>">
                                Edit
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Include Edit Modal -->
<?php include 'edit_user.php'; ?>

<script>
$(document).ready(function () {
    $(".btn-edit").click(function () {
        let userData = {
            ID: $(this).data("id"),
            Email: $(this).data("email"),
            First_Name: $(this).data("first-name"),
            Last_Name: $(this).data("last-name"),
            Department: $(this).data("department"),
            Status: $(this).data("status")
        };

        showEditModal(userData);
    });

    window.showEditModal = function (data) {
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

    $("#saveChanges").click(function (event) {
        event.preventDefault();

        let formData = new FormData($("#editForm")[0]);

        $.ajax({
            url: "update_user.php",
            type: "POST",
            data: formData,
            contentType: false,
            processData: false,
            success: function (response) {
                alert(response);
                location.reload();
            },
            error: function () {
                alert("Failed to update user.");
            }
        });
    });
});
</script>

</body>
</html>
