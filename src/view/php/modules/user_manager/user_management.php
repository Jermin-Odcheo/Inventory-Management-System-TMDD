<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');
include '../../general/header.php';
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if database connection is established
if (!isset($pdo)) {
    die("Database connection is not established.");
}

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../../../../public/index.php");
    exit();
}

// Define allowed sorting columns (for active users)
$allowedSortColumns = ['User_ID', 'Email', 'First_Name', 'Last_Name', 'Department', 'Status'];
$sortBy = isset($_GET['sort']) && in_array($_GET['sort'], $allowedSortColumns) ? $_GET['sort'] : 'User_ID';
$sortDir = isset($_GET['dir']) && $_GET['dir'] == 'desc' ? 'desc' : 'asc';

// Define departments array with both short codes and full names
$departments = [
    'SAS' => 'School of Advanced Studies',
    'SOM' => 'School of Medicine',
    'SOL' => 'School of Law',
    'STELA' => 'School of Teacher Education and Liberal Arts',
    'SONAHBS' => 'School of Nursing, Allied Health, and Biological Sciences',
    'SEA' => 'School of Engineering and Architecture',
    'SAMCIS' => 'School of Accountancy, Management, Computing, and Information Studies'
];

// Modify the query to include department and search filters
$query = "SELECT * FROM users WHERE is_deleted = 0";

// Add department filter if selected
if (isset($_GET['department']) && $_GET['department'] !== 'all') {
    $query .= " AND Department = :department";
}

// Add search filter if provided
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $query .= " AND (Email LIKE :search OR First_Name LIKE :search OR Last_Name LIKE :search)";
}

$query .= " ORDER BY `$sortBy` $sortDir";

try {
    $stmt = $pdo->prepare($query);

    if (isset($_GET['department']) && $_GET['department'] !== 'all') {
        $stmt->bindValue(':department', $_GET['department']);
    }

    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $stmt->bindValue(':search', '%' . $_GET['search'] . '%');
    }

    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$users) {
        $users = [];
    }
} catch (PDOException $e) {
    die("Database query error: " . $e->getMessage());
}

// Helper functions for sorting links/icons
function toggleDirection($currentSort, $currentDir, $column)
{
    return $currentSort === $column ? ($currentDir === 'asc' ? 'desc' : 'asc') : 'asc';
}

function sortIcon($currentSort, $column, $sortDir)
{
    if ($currentSort === $column) {
        return $sortDir === 'asc'
            ? ' <i class="bi bi-caret-up-fill"></i>'
            : ' <i class="bi bi-caret-down-fill"></i>';
    }
    return '';
}

// Add this function near the top of the file, after session_start()
function getCurrentUserRoles($pdo, $userId)
{
    $stmt = $pdo->prepare("
        SELECT r.Role_Name 
        FROM roles r 
        JOIN user_roles ur ON r.Role_ID = ur.Role_ID 
        WHERE ur.User_ID = ?
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Get current user's roles
$currentUserRoles = getCurrentUserRoles($pdo, $_SESSION['user_id']);

// Add this function to check if current user can delete target user
function canDeleteUser($currentUserRoles, $targetUserRoles)
{
    if (in_array('Super Admin', $currentUserRoles)) {
        return true;
    }
    if (in_array('Super User', $currentUserRoles)) {
        return count($targetUserRoles) === 1 && in_array('Regular User', $targetUserRoles);
    }
    return false;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- jQuery, Bootstrap CSS/JS, and Bootstrap Icons -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .main-content {
            margin-left: 300px; /* Adjust if you have a sidebar */
            padding: 20px;
            margin-bottom: 20px;
            width: auto;
        }

        .search-container {
            width: 250px;
        }

        .search-container input {
            padding-right: 30px;
        }

        .search-container i {
            color: #6c757d;
            pointer-events: none;
        }
    </style>
</head>
<body>
<div class="sidebar">
    <?php include '../../general/sidebar.php'; ?>
</div>
<!-- Main Content Area -->
<div class="main-content container-fluid">
    <h1>User Management</h1>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            User added successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['delete_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php
            if (isset($_SESSION['deleted_count'])) {
                echo "{$_SESSION['deleted_count']} user(s) have been successfully deleted.";
                unset($_SESSION['deleted_count']);
            } else {
                echo "User has been successfully deleted.";
            }
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php
        unset($_SESSION['delete_success']);
    endif;
    ?>

    <!-- Add this new div for delete messages -->
    <div id="deleteMessage" class="alert alert-success alert-dismissible fade show" style="display: none;" role="alert">
        <span id="deleteMessageText"></span>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>

    <div class="d-flex justify-content-end mb-3">
        <a href="add_user.php" class="btn btn-primary me-2">Add New User</a>
        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
            Delete My Account
        </button>
    </div>

    <!-- Add the filter controls -->
    <div class="row mb-3">
        <div class="col-md-6">
            <form class="d-flex" method="GET">
                <select name="department" class="form-select me-2" style="width: 400px;">
                    <option value="all">All Departments</option>
                    <?php foreach ($departments as $code => $name): ?>
                        <option value="<?php echo htmlspecialchars($code); ?>"
                            <?php echo (isset($_GET['department']) && $_GET['department'] === $code) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="search-container position-relative">
                    <input type="text" name="search" id="searchUsers" class="form-control"
                           placeholder="Search users..."
                           value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>"
                           style="width: 250px;">
                    <i class="bi bi-search position-absolute top-50 end-0 translate-middle-y me-2"></i>
                </div>
            </form>
        </div>
    </div>

    <div id="alertMessage"></div>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-dark">
            <tr>
                <th><input type="checkbox" id="select-all"></th>
                <th>
                    <a class="text-white text-decoration-none"
                       href="?sort=User_ID&dir=<?php echo toggleDirection($sortBy, $sortDir, 'User_ID'); ?>">
                        User ID<?php echo sortIcon($sortBy, 'User_ID', $sortDir); ?>
                    </a>
                </th>
                <th>
                    <a class="text-white text-decoration-none"
                       href="?sort=Email&dir=<?php echo toggleDirection($sortBy, $sortDir, 'Email'); ?>">
                        Email<?php echo sortIcon($sortBy, 'Email', $sortDir); ?>
                    </a>
                </th>
                <th>
                    <a class="text-white text-decoration-none"
                       href="?sort=First_Name&dir=<?php echo toggleDirection($sortBy, $sortDir, 'First_Name'); ?>">
                        Name<?php echo sortIcon($sortBy, 'First_Name', $sortDir); ?>
                    </a>
                </th>
                <th>
                    <a class="text-white text-decoration-none"
                       href="?sort=Department&dir=<?php echo toggleDirection($sortBy, $sortDir, 'Department'); ?>">
                        Department<?php echo sortIcon($sortBy, 'Department', $sortDir); ?>
                    </a>
                </th>
                <th>
                    <a class="text-white text-decoration-none"
                       href="?sort=Status&dir=<?php echo toggleDirection($sortBy, $sortDir, 'Status'); ?>">
                        Status<?php echo sortIcon($sortBy, 'Status', $sortDir); ?>
                    </a>
                </th>
                <th>Roles</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td>
                        <input type="checkbox" class="select-row" value="<?php echo $user['User_ID']; ?>">
                    </td>
                    <td><?php echo htmlspecialchars($user['User_ID']); ?></td>
                    <td><?php echo htmlspecialchars($user['Email']); ?></td>
                    <td><?php echo htmlspecialchars($user['First_Name'] . ' ' . $user['Last_Name']); ?></td>
                    <td><?php
                        echo htmlspecialchars(isset($departments[$user['Department']])
                            ? $departments[$user['Department']]
                            : $user['Department']);
                        ?></td>
                    <td><?php echo htmlspecialchars($user['Status'] ?? ''); ?></td>
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
                                data-status="<?php echo htmlspecialchars($user['Status']); ?>"
                                data-bs-toggle="modal" data-bs-target="#editUserModal">
                            Edit
                        </button>
                        <?php
                        $targetUserRoles = getCurrentUserRoles($pdo, $user['User_ID']);
                        $canDelete = canDeleteUser($currentUserRoles, $targetUserRoles);
                        if ($canDelete):
                            ?>
                            <button type="button" class="btn btn-sm btn-danger"
                                    data-id="<?php echo $user['User_ID']; ?>">
                                Delete
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div><!-- /.table-responsive -->
    <!-- Bulk action button for active users -->
    <div class="mb-3">
        <button type="button" id="delete-selected" class="btn btn-danger" style="display: none;" disabled>
            Delete Selected
        </button>
    </div>
</div><!-- /.main-content -->

<!-- Modal for editing user -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Form for editing user -->
                <form id="editUserForm" action="update_user.php" method="post">
                    <input type="hidden" id="editUserID" name="user_id">
                    <div class="mb-3">
                        <label for="editEmail" class="form-label">Email</label>
                        <input type="email" class="form-control" id="editEmail" name="email">
                    </div>
                    <div class="mb-3">
                        <label for="editFirstName" class="form-label">First Name</label>
                        <input type="text" class="form-control" id="editFirstName" name="first_name">
                    </div>
                    <div class="mb-3">
                        <label for="editLastName" class="form-label">Last Name</label>
                        <input type="text" class="form-control" id="editLastName" name="last_name">
                    </div>
                    <div class="mb-3">
                        <label for="editDepartment" class="form-label">Department</label>
                        <input type="text" class="form-control" id="editDepartment" name="department">
                    </div>
                    <div class="mb-3">
                        <label for="editPassword" class="form-label">Change Password (Leave blank to keep
                            current)</label>
                        <input type="password" class="form-control" id="editPassword" name="password">
                    </div>
                    <div class="mb-3">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div><!-- /.modal-body -->
        </div>
    </div>
</div>

<!-- Delete Account Modal -->
<div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-labelledby="deleteAccountModalLabel"
     aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteAccountModalLabel">Delete Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete your account? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteAccount">Delete</button>
            </div>
        </div>
    </div>
</div>

<!-- Custom JavaScript for bulk actions and form handling -->
<script>
    $(document).ready(function () {
        // Update bulk action buttons for active users
        function updateBulkActionButtons() {
            var activeCount = $(".select-row:checked").length;
            if (activeCount >= 2) {
                $("#delete-selected").prop("disabled", false).show();
            } else {
                $("#delete-selected").prop("disabled", true).hide();
            }
        }

        $(".select-row").change(updateBulkActionButtons);
        $("#select-all").change(function () {
            $(".select-row").prop("checked", $(this).prop("checked"));
            updateBulkActionButtons();
        });

        // Bulk delete handler
        $("#delete-selected").click(function () {
            let selected = [];
            $(".select-row:checked").each(function () {
                selected.push($(this).val());
            });
            if (selected.length > 0 && confirm("Are you sure you want to delete the selected users? They will be moved to archive.")) {
                $.ajax({
                    type: "POST",
                    url: "delete_user.php",
                    data: {
                        user_ids: selected,
                        action: "soft_delete"
                    },
                    dataType: 'json',
                    success: function (response) {
                        if (response.success) {
                            window.location.reload();
                        } else {
                            alert(response.message || "Failed to delete selected users. Please try again.");
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error("Error details:", {xhr, status, error});
                        alert("Failed to delete selected users. Error: " + error);
                    }
                });
            }
        });

        // Individual delete handler
        $(".btn-danger[data-id]").click(function () {
            let userId = $(this).data("id");
            if (confirm("Are you sure you want to delete this user? They will be moved to archive.")) {
                $.ajax({
                    type: "POST",
                    url: "delete_user.php",
                    data: {
                        user_id: userId,
                        action: "soft_delete"
                    },
                    dataType: 'json',
                    success: function (response) {
                        if (response.success) {
                            window.location.reload();
                        } else {
                            alert(response.message || "Failed to delete user. Please try again.");
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error("Error details:", {xhr, status, error});
                        alert("Failed to delete user. Error: " + error);
                    }
                });
            }
        });

        // Populate the edit modal when it's about to be shown
        $('#editUserModal').on('show.bs.modal', function (event) {
            // Button that triggered the modal
            var button = $(event.relatedTarget);
            // Retrieve data attributes from the clicked button
            var userId = button.data('id');
            var email = button.data('email');
            var firstName = button.data('firstName') || button.data('first-name');
            var lastName = button.data('lastName') || button.data('last-name');
            var department = button.data('department');

            console.log("Modal triggered with data:", {userId, email, firstName, lastName, department});

            // Populate the modal fields
            var modal = $(this);
            modal.find('#editUserID').val(userId);
            modal.find('#editEmail').val(email);
            modal.find('#editFirstName').val(firstName);
            modal.find('#editLastName').val(lastName);
            modal.find('#editDepartment').val(department);
        });

        // Intercept the edit form submission to use AJAX
        $("#editUserForm").on("submit", function (e) {
            e.preventDefault(); // Prevent the default form submission
            console.log("Edit form submit triggered:", $(this).serialize());
            $.ajax({
                type: "POST",
                url: $(this).attr("action"),
                data: $(this).serialize(),
                success: function (response) {
                    console.log("Response from server:", response);
                    $("#editUserModal").modal("hide");

                    // Get the updated user data from the form
                    var userId = $("#editUserID").val();
                    var email = $("#editEmail").val();
                    var firstName = $("#editFirstName").val();
                    var lastName = $("#editLastName").val();
                    var department = $("#editDepartment").val();

                    // Find and update the corresponding table row
                    var row = $("button.btn-edit[data-id='" + userId + "']").closest('tr');
                    row.find('td:eq(2)').text(email); // Update email cell
                    row.find('td:eq(3)').text(firstName + ' ' + lastName); // Update name cell
                    row.find('td:eq(4)').text(department); // Update department cell

                    // Update the edit button's data attributes
                    var editButton = row.find('.btn-edit');
                    editButton.data('email', email);
                    editButton.data('first-name', firstName);
                    editButton.data('last-name', lastName);
                    editButton.data('department', department);

                    // Show success message
                    $("#alertMessage").html(
                        '<div class="alert alert-success alert-dismissible fade show" role="alert">' +
                        response +
                        '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>'
                    );

                    // Automatically dismiss the alert after 3 seconds
                    setTimeout(function () {
                        $('.alert').fadeOut('slow', function () {
                            $(this).remove();
                        });
                    }, 3000);
                },
                error: function (xhr, status, error) {
                    console.error("AJAX Error:", error);
                    $("#alertMessage").html(
                        '<div class="alert alert-danger alert-dismissible fade show" role="alert">' +
                        'Error updating user: ' + error +
                        '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>'
                    );
                }
            });
        });

        // Handle delete account confirmation
        $("#confirmDeleteAccount").click(function () {
            $.ajax({
                type: "POST",
                url: "delete_account.php",
                data: {action: "delete_account"},
                success: function (response) {
                    if (response.success) {
                        alert("Account deleted successfully.");
                        window.location.href = "../../../../../public/index.php";
                    } else {
                        alert("Error: " + response.message);
                    }
                },
                error: function () {
                    alert("An error occurred while deleting the account.");
                }
            });
        });

        // Add this to handle the close button click
        $(document).on('click', '.btn-close', function () {
            $(this).closest('.alert').hide();
        });

        // Department filter change handler
        $('select[name="department"]').on('change', function () {
            this.form.submit();
        });

        // Search input handler with debounce
        let searchTimeout;
        $('#searchUsers').on('input', function () {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.form.submit();
            }, 500);
        });
    });

</script>

</body>
</html>
