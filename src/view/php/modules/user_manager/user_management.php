<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');
require_once('../../clients/admins/RBACService.php');

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../../../../public/index.php");
    exit();
}

// Initialize RBAC
$rbac = new RBACService($pdo, $_SESSION['user_id']);

// Define available actions based on privileges
$canCreate = $rbac->hasPrivilege('User Management', 'Create');
$canModify = $rbac->hasPrivilege('User Management', 'Modify');
$canDelete = $rbac->hasPrivilege('User Management', 'Remove');
$canTrack = $rbac->hasPrivilege('User Management', 'Track');

include '../../general/header.php';

// Get current user's roles and initialize RBAC
$currentUserRoles = getCurrentUserRoles($pdo, $_SESSION['user_id']);
$rbac = new RBACManager($pdo, $currentUserRoles);

// Check view permission immediately
if (!$rbac->hasPrivilege('User Management', 'View')) {
    header("Location: ../../../../../public/index.php");
    exit();
}

// Get permissions for use in template
$canEdit = $rbac->hasPrivilege('User Management', 'Edit');
$canDelete = $rbac->hasPrivilege('User Management', 'Delete');

// Define allowed sorting columns (for active users)
$allowedSortColumns = ['id', 'Email', 'First_Name', 'Last_Name', 'Department', 'Status'];
$sortBy = isset($_GET['sort']) && in_array($_GET['sort'], $allowedSortColumns) ? $_GET['sort'] : 'id';
$sortDir = isset($_GET['dir']) && $_GET['dir'] == 'desc' ? 'desc' : 'asc';

// Fetch departments from database
$departments = [];
try {
    $deptStmt = $pdo->query("SELECT id, department_name, abbreviation FROM departments WHERE is_disabled = 0 ORDER BY department_name");
    while ($dept = $deptStmt->fetch(PDO::FETCH_ASSOC)) {
        $departments[$dept['id']] = [
            'name' => $dept['department_name'],
            'abbreviation' => $dept['abbreviation']
        ];
    }
} catch (PDOException $e) {
    error_log("Error fetching departments: " . $e->getMessage());
}

// Function to get user departments
function getUserDepartments($pdo, $userId)
{
    $deptIds = [];
    try {
        $stmt = $pdo->prepare("SELECT department_id FROM user_departments WHERE user_id = ?");
        $stmt->execute([$userId]);
        $deptIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log("Error fetching user departments: " . $e->getMessage());
    }
    return $deptIds;
}

// Build the query to include department and search filters, plus the is_disabled=0 check
$query = "SELECT u.id, u.email, u.first_name, u.last_name, u.status AS Status
            FROM users u";

if (isset($_GET['department']) && $_GET['department'] !== 'all') {
    $query .= "
        JOIN user_departments ud ON u.id = ud.user_id
       WHERE ud.department_id = :department
         AND u.is_disabled = 0";
} else {
    $query .= "
       WHERE u.is_disabled = 0";
}

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $query .= " 
       AND (u.email LIKE :search 
         OR u.first_name LIKE :search 
         OR u.last_name LIKE :search)";
}

$query .= " 
   ORDER BY `$sortBy` $sortDir";

try {
    $stmt = $pdo->prepare($query);
    if (isset($_GET['department']) && $_GET['department'] !== 'all') {
        $stmt->bindValue(':department', $_GET['department'], PDO::PARAM_INT);
    }
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $stmt->bindValue(':search', '%' . $_GET['search'] . '%', PDO::PARAM_STR);
    }
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

function sortIcon($currentSort, $column, $sortDir)
{
    if ($currentSort === $column) {
        return $sortDir === 'asc'
            ? ' <i class="bi bi-caret-up-fill"></i>'
            : ' <i class="bi bi-caret-down-fill"></i>';
    }
    return '';
}

function getCurrentUserRoles($pdo, $userId)
{
    $stmt = $pdo->prepare("
        SELECT r.Role_Name 
        FROM roles r 
        JOIN user_roles ur ON r.id = ur.Role_ID 
        WHERE ur.user_id = ?
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

class RBACManager
{
    private $pdo;
    private $userRoles;

    public function __construct(PDO $pdo, array $userRoles)
    {
        $this->pdo = $pdo;
        $this->userRoles = $userRoles;
    }

    public function hasPrivilege(string $moduleName, string $privilegeName): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM privileges p 
                JOIN role_module_privileges rmp ON p.id = rmp.privilege_id 
                JOIN roles r ON r.id = rmp.role_id
                JOIN modules m ON m.id = rmp.module_id
                WHERE r.role_name IN (" . str_repeat('?,', count($this->userRoles) - 1) . "?)
                  AND m.module_name = ?
                  AND p.priv_name = ?
                  AND p.is_disabled = 0
                  AND r.is_disabled = 0
            ");
            $params = array_merge($this->userRoles, [$moduleName, $privilegeName]);
            $stmt->execute($params);
            return (int)$stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            $this->logError("Permission check failed", $e);
            return false;
        }
    }

    public function canDeleteUser(int $targetUserId): bool
    {
        try {
            if (!$this->hasPrivilege('User Management', 'Delete')) {
                return false;
            }
            $targetRoles = $this->getUserRoles($targetUserId);
            $currentUserRoles = $this->userRoles;
            $roleHierarchy = $this->getRoleHierarchy();
            $currentUserMaxLevel = $this->getMaxRoleLevel($currentUserRoles, $roleHierarchy);
            $targetUserMaxLevel = $this->getMaxRoleLevel($targetRoles, $roleHierarchy);
            return $currentUserMaxLevel > $targetUserMaxLevel;
        } catch (Exception $e) {
            $this->logError("Delete permission check failed", $e);
            return false;
        }
    }

    private function getMaxRoleLevel(array $userRoles, array $hierarchy): int
    {
        $maxLevel = 0;
        foreach ($userRoles as $role) {
            $maxLevel = max($maxLevel, $hierarchy[$role] ?? 0);
        }
        return $maxLevel;
    }

    private function getUserRoles(int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT r.role_name 
            FROM roles r 
            JOIN user_roles ur ON r.id = ur.role_id 
            WHERE ur.user_id = ?
              AND r.is_disabled = 0
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function canBulkDelete(array $targetUserIds): array
    {
        $results = [
            'can_delete' => true,
            'unauthorized_users' => []
        ];
        foreach ($targetUserIds as $targetId) {
            if (!$this->canDeleteUser($targetId)) {
                $results['can_delete'] = false;
                $results['unauthorized_users'][] = $targetId;
            }
        }
        return $results;
    }

    private function logError($message, Exception $e)
    {
        error_log(sprintf("[RBAC Error] %s: %s", $message, $e->getMessage()));
    }

    private function getRoleHierarchy(): array
    {
        return [
            'Admin' => 3,
            'Manager' => 2,
            'Staff' => 1,
        ];
    }
}

$rbac = new RBACManager($pdo, $currentUserRoles);
$canEdit = $rbac->hasPrivilege('User Management', 'Edit');
$canDelete = $rbac->hasPrivilege('User Management', 'Delete');

if ($canEdit) {
    echo '<button class="edit-btn">Edit</button>';
}
if ($canDelete) {
    echo '<button class="delete-btn">Delete</button>';
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
            margin-left: 300px;
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
        .main-content.container-fluid {
            padding: 100px 15px;
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
    <div id="deleteMessage" class="alert alert-success alert-dismissible fade show" style="display: none;" role="alert">
        <span id="deleteMessageText"></span>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <!-- Modal for adding a new user -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
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
                                        <?php echo htmlspecialchars($name['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="custom">Custom Department</option>
                            </select>
                            <input type="text" id="modal_custom_department" name="custom_department"
                                   class="form-control" style="display: none;" placeholder="Enter custom department">
                        </div>
                        <fieldset class="mb-3">
                            <legend>Assign Roles: <span class="text-danger">*</span></legend>
                            <div class="text-muted mb-2">At least one role must be selected</div>
                            <?php
                            $stmt = $pdo->prepare("SELECT * FROM roles");
                            $stmt->execute();
                            $modal_roles = $stmt->fetchAll();
                            foreach ($modal_roles as $role): ?>
                                <div class="form-check">
                                    <input type="checkbox" name="roles[]" value="<?php echo $role['id']; ?>"
                                           id="modal_role_<?php echo $role['id']; ?>"
                                           class="form-check-input modal-role-checkbox">
                                    <label for="modal_role_<?php echo $role['id']; ?>" class="form-check-label">
                                        <?php echo htmlspecialchars($role['role_name']); ?>
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
    <!-- FILTER SEARCH AND ADD USER BUTTON -->
    <div class="row mb-3">
        <div class="col-md-8">
            <form class="d-flex" method="GET">
                <select name="department" class="form-select me-2 department-filter">
                    <option value="all">All Departments</option>
                    <?php foreach ($departments as $code => $name): ?>
                        <option value="<?php echo htmlspecialchars($code); ?>"
                            <?php echo (isset($_GET['department']) && $_GET['department'] == $code) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($name['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="search-container position-relative">
                    <input type="text" name="search" id="searchUsers" class="form-control"
                           placeholder="Search users..."
                           value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                    <i class="bi bi-search position-absolute top-50 end-0 translate-middle-y me-2"></i>
                </div>
            </form>
        </div>
        <div class="col-md-4 d-flex justify-content-end">
            <button type="button" class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#addUserModal">
                Create New User
            </button>
        </div>
    </div>
    <div class="table-responsive" id="table">
        <table class="table table-striped table-hover" id="umTable">
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
                <tr data-user-id="<?php echo htmlspecialchars($user['id']); ?>">
                    <td><input type="checkbox" class="select-row" value="<?php echo $user['id']; ?>"></td>
                    <td><?php echo htmlspecialchars($user['id']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                    <td>
                        <?php
                        $userDeptIds = getUserDepartments($pdo, $user['id']);
                        if (!empty($userDeptIds)) {
                            $deptNames = [];
                            foreach ($userDeptIds as $deptId) {
                                if (isset($departments[$deptId])) {
                                    $deptNames[] = $departments[$deptId]['abbreviation'];
                                }
                            }
                            echo htmlspecialchars(implode(', ', $deptNames));
                        } else {
                            echo "Not assigned";
                        }
                        ?>
                    </td>
                    <td><?php echo htmlspecialchars($user['Status'] ?? ''); ?></td>
                    <td>
                        <?php
                        $stmtRole = $pdo->prepare("
                            SELECT r.Role_Name
                            FROM roles r 
                            JOIN user_roles ur ON r.id = ur.Role_ID 
                            WHERE ur.User_ID = ?
                        ");
                        $stmtRole->execute([$user['id']]);
                        $roles = $stmtRole->fetchAll(PDO::FETCH_COLUMN);
                        echo implode(', ', $roles);
                        ?>
                    </td>
                    <td>
                        <?php if ($canModify): ?>
                            <button type="button" class="btn btn-sm btn-warning btn-edit"
                                    data-bs-toggle="modal" data-bs-target="#editUserModal"
                                    data-id="<?php echo htmlspecialchars($user['id']); ?>"
                                    data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                    data-first-name="<?php echo htmlspecialchars($user['first_name']); ?>"
                                    data-last-name="<?php echo htmlspecialchars($user['last_name']); ?>"
                                    data-department="<?php echo htmlspecialchars($userDeptIds[0] ?? ''); ?>">
                                Modify
                            </button>
                        <?php endif; ?>
                        <?php if ($canDelete): ?>
                            <button type="button" class="btn btn-sm btn-danger delete-user"
                                    data-id="<?php echo htmlspecialchars($user['id']); ?>">
                                Remove
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <!-- Pagination Controls -->
    <div class="container-fluid">
        <div class="row align-items-center g-3">
            <div class="col-12 col-sm-auto">
                <div class="text-muted">
                    Showing <span id="currentPage">1</span> to <span id="rowsPerPage">20</span> of <span id="totalRows">100</span> entries
                </div>
            </div>
            <div class="col-12 col-sm-auto ms-sm-auto">
                <div class="d-flex align-items-center gap-2">
                    <button id="prevPage" class="btn btn-outline-primary d-flex align-items-center gap-1">
                        <i class="bi bi-chevron-left"></i> Previous
                    </button>
                    <select id="rowsPerPageSelect" class="form-select" style="width: auto;">
                        <option value="10" selected>10</option>
                        <option value="20">20</option>
                        <option value="30">30</option>
                        <option value="50">50</option>
                    </select>
                    <button id="nextPage" class="btn btn-outline-primary d-flex align-items-center gap-1">
                        Next <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-12">
                    <ul class="pagination justify-content-center" id="pagination"></ul>
                </div>
            </div>
        </div>
    </div>
    <!-- Bulk action button for active users -->
    <div class="mb-3">
        <button type="button" id="delete-selected" class="btn btn-danger" style="display: none;" disabled>
            Delete Selected
        </button>
    </div>
</div>
<!-- Confirm Delete Modal -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmDeleteModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="confirmDeleteMessage"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteButton">Delete</button>
            </div>
        </div>
    </div>
</div>
<!-- Modal for editing user -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
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
                        <select class="form-select shadow-sm" id="editDepartment" name="department">
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept_id => $dept_info): ?>
                                <option value="<?php echo htmlspecialchars($dept_id); ?>">
                                    <?php echo htmlspecialchars($dept_info['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <style>
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
                        <label for="editPassword" class="form-label">
                            Change Password (Leave blank to keep current)
                        </label>
                        <input type="password" class="form-control" id="editPassword" name="password">
                    </div>
                    <div class="mb-3">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script>$(document).ready(function () {
        // Helper: Build a cache-busted URL for reloading content.
        function getCacheBustedUrl(selector) {
            var baseUrl = location.href.split('#')[0];
            var connector = (baseUrl.indexOf('?') > -1) ? '&' : '?';
            return baseUrl + connector + '_=' + new Date().getTime() + ' ' + selector;
        }

        // Toggle custom department input
        $('#modal_department').on('change', function () {
            if ($(this).val() === 'custom') {
                $('#modal_custom_department').show().attr('required', true);
            } else {
                $('#modal_custom_department').hide().attr('required', false);
            }
        });

        // Handle "Add User" form submission via AJAX
        $('#addUserForm').on('submit', function (e) {
            e.preventDefault();
            <?php if (!$canCreate): ?>
            showToast('danger', 'You do not have permission to create users');
            return false;
            <?php endif; ?>
            var actionUrl = $(this).attr('action');
            $.ajax({
                url: actionUrl,
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        $("#addUserModal").modal('hide');
                        // Reload only the table body with a cache-busted URL.
                        $('#umTable tbody').load(getCacheBustedUrl('#umTable tbody > *'), function () {
                            showToast(response.message, 'success');
                        });
                        $('#addUserForm')[0].reset();
                    } else {
                        showToast(response.message, 'error');
                    }
                },
                error: function () {
                    showToast('Error adding user.', 'error');
                }
            });
        });

        var deleteAction = null;
        // Single-user delete
        $(document).on('click', '.delete-user', function () {
            const userId = $(this).data("id");
            deleteAction = {type: 'single', userId: userId};
            $('#confirmDeleteMessage').text("Are you sure you want to archive this user?");
            $('#confirmDeleteModal').modal('show');
        });

        // Bulk delete
        $("#delete-selected").click(function () {
            const selected = $(".select-row:checked").map(function () {
                return $(this).val();
            }).get();
            if (selected.length === 0) {
                showToast('Please select users to archive.', 'warning');
                return;
            }
            deleteAction = {type: 'bulk', selected: selected};
            $('#confirmDeleteMessage').text(`Are you sure you want to archive ${selected.length} selected user(s)?`);
            $('#confirmDeleteModal').modal('show');
        });

        // Delete account confirmation
        $("#confirmDeleteAccount").click(function () {
            deleteAction = {type: 'account'};
            $('#confirmDeleteMessage').text("Are you sure you want to delete your account?");
            $('#confirmDeleteModal').modal('show');
            $('#delete-selected').modal('hide');
        });

        // Confirm delete action
        $('#confirmDeleteButton').on('click', function () {
            $(this).blur();
            $('#confirmDeleteModal').modal('hide');
            var currentAction = deleteAction;
            deleteAction = null;
            if (currentAction) {
                if (currentAction.type === 'single') {
                    $.ajax({
                        type: "POST",
                        url: "delete_user.php",
                        data: {user_id: currentAction.userId},
                        dataType: 'json',
                        success: function (response) {
                            if (response.success) {
                                $('#umTable tbody').load(getCacheBustedUrl('#umTable tbody > *'), function () {
                                    showToast(response.message, 'success');
                                });
                            } else {
                                showToast(response.message, 'error');
                            }
                        },
                        error: function () {
                            showToast('Error deleting user.', 'error');
                        }
                    });
                } else if (currentAction.type === 'bulk') {
                    $.ajax({
                        type: "POST",
                        url: "delete_user.php",
                        data: {user_ids: currentAction.selected},
                        dataType: 'json',
                        success: function (response) {
                            if (response.success) {
                                $('#umTable tbody').load(getCacheBustedUrl('#umTable tbody > *'), function () {
                                    showToast(response.message, 'success');
                                });
                            } else {
                                showToast(response.message, 'error');
                            }
                        },
                        error: function () {
                            showToast('Error deleting users.', 'error');
                        }
                    });
                } else if (currentAction.type === 'account') {
                    $.ajax({
                        type: "POST",
                        url: "delete_account.php",
                        data: {action: "delete_account"},
                        dataType: 'json',
                        success: function (response) {
                            if (response.success) {
                                $('#umTable tbody').load(getCacheBustedUrl('#umTable tbody > *'), function () {
                                    showToast(response.message, 'success');
                                });
                            } else {
                                showToast(response.message, 'error');
                            }
                        },
                        error: function () {
                            showToast('Error deleting account.', 'error');
                        }
                    });
                }
            }
        });

        // Remove lingering modal backdrop for delete modal
        $('#confirmDeleteModal').on('hidden.bs.modal', function () {
            $('body').removeClass('modal-open');
            $('.modal-backdrop').remove();
        });

        // Remove lingering backdrop for edit modal as well
        $('#editUserModal').on('hidden.bs.modal', function () {
            $('body').removeClass('modal-open');
            $('.modal-backdrop').remove();
        });

        $(document).on('click', '.btn-close', function () {
            $(this).closest('.alert').hide();
        });

        $('.department-filter').on('change', function () {
            this.form.submit();
        });

        let searchTimeout;
        $('#searchUsers').on('input', function () {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.form.submit();
            }, 500);
        });

        // Populate Edit Modal with existing data
        $('#editUserModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var userId = button.data('id');
            var email = button.data('email');
            var firstName = button.data('first-name');
            var lastName = button.data('last-name');
            var department = button.data('department');
            var modal = $(this);
            modal.find('#editUserID').val(userId);
            modal.find('#editEmail').val(email);
            modal.find('#editFirstName').val(firstName);
            modal.find('#editLastName').val(lastName);
            modal.find('#editDepartment').val(department);
        });

        // Handle "Edit User" form submission via AJAX
        $("#editUserForm").on("submit", function (e) {
            e.preventDefault();
            var submitButton = $(this).find('button[type="submit"]');
            submitButton.prop('disabled', true).html(
                '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...'
            );
            $.ajax({
                type: "POST",
                url: "update_user.php",
                data: $(this).serialize(),
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        // Instead of manually updating the row, reload the table body to get updated data
                        $('#umTable tbody').load(getCacheBustedUrl('#umTable tbody > *'), function () {
                            showToast(response.message, 'success');
                        });
                    } else {
                        showToast(response.message, 'error');
                    }
                },
                error: function () {
                    showToast('Error updating user.', 'error');
                },
                complete: function () {
                    // Ensure the edit modal is hidden and remove any lingering backdrop
                    $("#editUserModal").modal('hide');
                    submitButton.prop('disabled', false).text('Save Changes');
                    $('body').removeClass('modal-open');
                    $('.modal-backdrop').remove();
                }
            });
        });

        // Handle "Select All" checkbox
        $("#select-all").on('click', function () {
            $(".select-row").prop('checked', $(this).prop('checked'));
            toggleBulkDeleteButton();
        });
        $(".select-row").on('change', function () {
            toggleBulkDeleteButton();
        });
        function toggleBulkDeleteButton() {
            const anyChecked = $(".select-row:checked").length > 1;
            $("#delete-selected").prop('disabled', !anyChecked).toggle(anyChecked);
        }
    });

</script>
<script type="text/javascript" src="<?php echo BASE_URL; ?>src/control/js/pagination.js" defer></script>
<?php include '../../general/footer.php'; ?>
</body>
</html>
