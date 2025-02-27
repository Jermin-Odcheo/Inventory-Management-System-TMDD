<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');
// include '../../general/header.php';
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if database connection is established
if (!isset($pdo)) {
    die("Database connection is not established.");
}

// Check if the user is logged in
// if (!isset($_SESSION['user_id'])) {
//     header("Location: ../../../../../public/index.php");
//     exit();
// }

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
// $currentUserRoles = getCurrentUserRoles($pdo, $_SESSION['user_id']);
$currentUserRoles = ["Regular User"];

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

/*
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
RBAC : view
if role doesnt include view for User module then redirect
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
*/
try {
    $stmt = $pdo->prepare("
        select privilege_name 
        from privileges as p 
        join role_privileges as rp on p.privilege_id = rp.privilege_id 
        join roles as r on r.role_id = rp.role_id
        where rp.role_id = (select r.role_id where role_name = 'Regular User') 
    ");
    $stmt->execute();
    $privs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $privNames = array_column($privs, 'privilege_name');
    if (empty($privNames) || !in_array("View", $privNames)) { //redirect to home if you got no privs for this page
        header("Location: ../../../../../public/index.php");
        exit();
    }
} catch (PDOException $e) {
    die("Database query error: " . $e->getMessage());
}
/*
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
RBAC : edit
if role doesnt include edit then remove those edit buttons
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
*/
$showEditButton = false;
try {
    $stmt = $pdo->prepare("
        select privilege_name 
        from privileges as p 
        join role_privileges as rp on p.privilege_id = rp.privilege_id 
        join roles as r on r.role_id = rp.role_id
        where rp.role_id = (select r.role_id where role_name = 'Regular User') 
    ");
    $stmt->execute();
    $privs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $privNames = array_column($privs, 'privilege_name');
    if (!empty($privNames) && in_array("Edit", $privNames)) {
        //show edit button if privileges are not empty and edit is in them
        $showEditButton = true;
    }
} catch (PDOException $e) {
    die("Database query error: " . $e->getMessage());
}
/*
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
if role doesnt include delete then remove the delete option thingy
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
*/

/*
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
if role doesnt include create then remove the add new user
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
*/
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
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>src/view/styles/css/pagination.css">
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
        <button type="button" class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#addUserModal">
            Add New User
        </button>
        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
            Delete My Account
        </button>
    </div>
    <!-- Modal for adding a new user -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addUserModalLabel">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addUserForm" method="POST" action="add_user.php">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email:</label>
                            <input type="email" name="email" id="email" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password:</label>
                            <input type="password" name="password" id="password" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label for="first_name" class="form-label">First Name:</label>
                            <input type="text" name="first_name" id="first_name" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label for="last_name" class="form-label">Last Name:</label>
                            <input type="text" name="last_name" id="last_name" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label for="modal_department" class="form-label">Department:</label>
                            <select name="department" id="modal_department" class="form-select mb-2" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $code => $name): ?>
                                    <option value="<?php echo htmlspecialchars($code); ?>">
                                        <?php echo htmlspecialchars($name); ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="custom">Custom Department</option>
                            </select>
                            <input type="text" id="modal_custom_department" name="custom_department"
                                   class="form-control"
                                   style="display: none;"
                                   placeholder="Enter custom department">
                        </div>

                        <fieldset class="mb-3">
                            <legend>Assign Roles: <span class="text-danger">*</span></legend>
                            <div class="text-muted mb-2">At least one role must be selected</div>
                            <?php
                            // Fetch roles for the modal
                            $stmt = $pdo->prepare("SELECT * FROM roles");
                            $stmt->execute();
                            $modal_roles = $stmt->fetchAll();

                            foreach ($modal_roles as $role): ?>
                                <div class="form-check">
                                    <input type="checkbox" name="roles[]" value="<?php echo $role['Role_ID']; ?>"
                                           id="modal_role_<?php echo $role['Role_ID']; ?>"
                                           class="form-check-input modal-role-checkbox">
                                    <label for="modal_role_<?php echo $role['Role_ID']; ?>" class="form-check-label">
                                        <?php echo htmlspecialchars($role['Role_Name']); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </fieldset>

                        <div class="mb-3">
                            <button type="submit" class="btn btn-primary">Add User</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Add the filter controls -->
    <div class="row mb-3">
        <div class="col-md-6">
            <form class="d-flex" method="GET">
                <select name="department" class="form-select me-2 department-filter" style="width: 400px;">
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
        <table class="table table-striped table-hover" id="table">
            <thead class="table-dark">
            <tr>
                <th><input type="checkbox" id="select-all"></th>
                <th>
                    <a class="text-white text-decoration-none"
                       href="?sort=User_ID&dir=<?php echo toggleDirection($sortBy, $sortDir, 'User_ID'); ?>">
                        #<?php echo sortIcon($sortBy, 'User_ID', $sortDir); ?>
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
                        <?php if ($showEditButton): ?>
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
                        <?php endif; ?>
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
    <!-- Pagination Controls -->
    <div class="container-fluid">
        <div class="row align-items-center g-3">
            <!-- Pagination Info -->
            <div class="col-12 col-sm-auto">
                <div class="text-muted">
                    Showing <span id="currentPage">1</span> to <span id="rowsPerPage">20</span> of <span
                            id="totalRows">100</span> entries
                </div>
            </div>

            <!-- Pagination Controls -->
            <div class="col-12 col-sm-auto ms-sm-auto">
                <div class="d-flex align-items-center gap-2">
                    <button id="prevPage" class="btn btn-outline-primary d-flex align-items-center gap-1">
                        <i class="bi bi-chevron-left"></i>
                        Previous
                    </button>

                    <select id="rowsPerPageSelect" class="form-select" style="width: auto;">
                        <option value="10" selected>10</option>
                        <option value="20">20</option>
                        <option value="30">30</option>
                        <option value="50">50</option>
                    </select>

                    <button id="nextPage" class="btn btn-outline-primary d-flex align-items-center gap-1">
                        Next
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
        <!-- New Pagination Page Numbers -->
        <div class="row mt-3">
            <div class="col-12">
                <ul class="pagination justify-content-center" id="pagination"></ul>
            </div>
        </div>
    </div> <!-- /.End of Pagination -->

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
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="editDepartment" class="form-label">Department</label>
                        <select class="form-select shadow-sm" id="editDepartment" name="department"
                                style="height: 38px; padding: 0.375rem 2.25rem 0.375rem 0.75rem; font-size: 1rem; border-radius: 0.25rem; border: 1px solid #ced4da;">
                            <option value="">Select Department</option>
                            <option value="Office of the President">Office of the President</option>
                            <option value="Office of the Executive Assistant to the President">Office of the Executive
                                Assistant to the President
                            </option>
                            <option value="Office of the Internal Auditor">Office of the Internal Auditor</option>

                            <optgroup label="Mission and Identity Cluster" style="font-weight: 600; color: #6c757d;">
                                <option value="Office of the Vice President for Mission and Identity">Office of the Vice
                                    President for Mission and Identity
                                </option>
                                <option value="Center for Campus Ministry">Center for Campus Ministry</option>
                                <option value="Community Extension and Outreach Programs Office">Community Extension and
                                    Outreach Programs Office
                                </option>
                                <option value="St. Aloysius Gonzaga Parish Office">St. Aloysius Gonzaga Parish Office
                                </option>
                                <option value="Sunflower Child and Youth Wellness Center">Sunflower Child and Youth
                                    Wellness Center
                                </option>
                            </optgroup>

                            <optgroup label="Academic Cluster" style="font-weight: 600; color: #6c757d;">
                                <option value="Office of the Vice President for Academic Affairs">Office of the Vice
                                    President for Academic Affairs
                                </option>
                                <option value="SAMCIS">School of Accountancy, Management, Computing and Information
                                    Studies (SAMCIS)
                                </option>
                                <option value="SAS">School of Advanced Studies (SAS)</option>
                                <option value="SEA">School of Engineering and Architecture (SEA)</option>
                                <option value="SOL">School of Law (SOL)</option>
                                <option value="SOM">School of Medicine (SOM)</option>
                                <option value="SONAHBS">School of Nursing, Allied Health, and Biological Sciences
                                    Natural Sciences (SONAHBS)
                                </option>
                                <option value="STELA">School of Teacher Education and Liberal Arts (STELA)</option>
                                <option value="SLU BEdS">Basic Education School (SLU BEdS)</option>
                            </optgroup>

                            <optgroup label="External Relations, Media and Communications and Alumni Affairs"
                                      style="font-weight: 600; color: #6c757d;">
                                <option value="Office of Institutional Development and Quality Assurance">Office of
                                    Institutional Development and Quality Assurance
                                </option>
                                <option value="University Libraries">University Libraries</option>
                                <option value="University Registrar's Office">University Registrar's Office</option>
                                <option value="University Research and Innovation Center">University Research and
                                    Innovation Center
                                </option>
                            </optgroup>

                            <optgroup label="Finance Cluster" style="font-weight: 600; color: #6c757d;">
                                <option value="Office of the Vice President for Finance">Office of the Vice President
                                    for Finance
                                </option>
                                <option value="Asset Management and Inventory Control Office">Asset Management and
                                    Inventory Control Office
                                </option>
                                <option value="Finance Office">Finance Office</option>
                                <option value="Printing Operations Office">Printing Operations Office</option>
                                <option value="TMDD">Technology Management and Development Department (TMDD)</option>
                            </optgroup>

                            <optgroup label="Administration Cluster" style="font-weight: 600; color: #6c757d;">
                                <option value="Office of the Vice President for Administration">Office of the Vice
                                    President for Administration
                                </option>
                                <option value="Athletics and Fitness Center">Athletics and Fitness Center</option>
                                <option value="CPMSD">Campus Planning, Maintenance, and Security Department (CPMSD)
                                </option>
                                <option value="CCA">Center for Culture and the Arts (CCA)</option>
                                <option value="Dental Clinic">Dental Clinic</option>
                                <option value="Guidance Center">Guidance Center</option>
                                <option value="HRD">Human Resource Department (HRD)</option>
                                <option value="Students' Residence Hall">Students' Residence Hall</option>
                                <option value="Medical Clinic">Medical Clinic</option>
                                <option value="OLA">Office for Legal Affairs (OLA)</option>
                                <option value="OSA">Office of Student Affairs (OSA)</option>
                            </optgroup>
                        </select>
                    </div>

                    <style>
                        /* Custom styles for the select dropdown */
                        .form-select {
                            appearance: none;
                            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
                        }

                        .form-select:focus {
                            border-color: #86b7fe;
                            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
                        }

                        .form-select option {
                            padding: 10px;
                        }

                        .form-select optgroup {
                            margin-top: 10px;
                        }
                    </style>
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

        $('.department-filter').on('change', function () {
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
<script type="text/javascript" src="<?php echo BASE_URL; ?>src/control/js/pagination.js" defer></script>
</body>
</html>
