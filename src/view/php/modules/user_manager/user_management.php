<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');
require_once('../../clients/admins/RBACService.php');

// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

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
include '../../general/sidebar.php';
include '../../general/footer.php';

// Get current user's roles and initialize RBAC manager
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

// Define allowed sort columns mapping to actual SQL expressions
$sortColumnMap = [
    'id' => 'u.id',
    'Email' => 'u.email',
    'First_Name' => 'u.first_name',
    'Last_Name' => 'u.last_name',
    'Department' => 'd.department_name',
    'Status' => 'u.status'
];

// Use allowed sort columns from GET parameters
$sortBy = (isset($_GET['sort']) && isset($sortColumnMap[$_GET['sort']])) ? $_GET['sort'] : 'id';
$sortDir = (isset($_GET['dir']) && $_GET['dir'] == 'desc') ? 'desc' : 'asc';

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

// Define filters based on GET parameters, using trim to remove extra spaces
$hasDepartmentFilter = isset($_GET['department']) && $_GET['department'] !== 'all';
$hasSearchFilter = isset($_GET['search']) && strlen(trim($_GET['search'])) > 0;

// Build the query to include department and search filters, plus the is_disabled=0 check
$query = "SELECT DISTINCT 
            u.id, 
            u.email, 
            u.first_name, 
            u.last_name, 
            u.status AS Status
          FROM users u
          LEFT JOIN user_departments ud ON u.id = ud.user_id
          LEFT JOIN departments d ON ud.department_id = d.id
          LEFT JOIN user_roles ur ON u.id = ur.user_id
          LEFT JOIN roles r ON ur.Role_ID = r.id
          WHERE u.is_disabled = 0";

if ($hasDepartmentFilter) {
    $query .= " AND ud.department_id = :department";
}

if ($hasSearchFilter) {
    $query .= " AND (
        u.email LIKE :search1
        OR u.first_name LIKE :search2
        OR u.last_name LIKE :search3
        OR d.department_name LIKE :search4
        OR d.abbreviation LIKE :search5
        OR r.Role_Name LIKE :search6
    )";
}

// Apply ordering using the mapped sort column
$query .= " ORDER BY " . $sortColumnMap[$sortBy] . " " . $sortDir;

try {
    $stmt = $pdo->prepare($query);

    if ($hasDepartmentFilter) {
        $departmentId = filter_var($_GET['department'], FILTER_VALIDATE_INT);
        if ($departmentId === false) {
            die("Invalid department ID.");
        }
        $stmt->bindValue(':department', $departmentId, PDO::PARAM_INT);
    }

    if ($hasSearchFilter) {
        $searchTerm = '%' . trim($_GET['search']) . '%';
        $stmt->bindValue(':search1', $searchTerm, PDO::PARAM_STR);
        $stmt->bindValue(':search2', $searchTerm, PDO::PARAM_STR);
        $stmt->bindValue(':search3', $searchTerm, PDO::PARAM_STR);
        $stmt->bindValue(':search4', $searchTerm, PDO::PARAM_STR);
        $stmt->bindValue(':search5', $searchTerm, PDO::PARAM_STR);
        $stmt->bindValue(':search6', $searchTerm, PDO::PARAM_STR);
    }

    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$users) {
        $users = [];
    }
} catch (PDOException $e) {
    error_log("Database query error: " . $e->getMessage());
    die("An error occurred while fetching users. Please try again later.");
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
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>src/view/styles/css/pagination.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>src/view/styles/css/user_roles_management.css">
    <title>Manage Users</title>
</head>
<body>
<!-- Main Content Area -->
<div class="main-content container-fluid">
    <header>
        <h1>USER MANAGER</h1>
    </header>
    <!-- FILTER SEARCH AND ADD USER BUTTON -->
    <div class="filters-container">
        <div class="search-filter">
            <label for="search-filters">Search Users</label>
            <input type="text" name="search" id="search-filters"
                   placeholder="Search users by email, first name, or last name..."
                   value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
        </div>
        <div class="filter-container">
            <label for="department-filter">Filter by Department</label>
            <select name="department" id="department-filter">
                <option value="all">All Departments</option>
                <?php foreach ($departments as $code => $name): ?>
                    <option value="<?php echo htmlspecialchars($code); ?>"
                        <?php echo (isset($_GET['department']) && $_GET['department'] == $code) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($name['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="action-buttons">
            <button id="create-btn" type="button" class="btn btn-primary me-2" data-bs-toggle="modal"
                    data-bs-target="#createUserModal">
                Create New User
            </button>
        </div>
    </div>
    <div class="table-responsive" id="table">
        <table class="table table-striped table-hover" id="umTable">
            <thead>
            <tr>
                <th><input type="checkbox" id="select-all"></th>
                <th>
                    <a class="text-black text-decoration-none"
                       href="?sort=id&dir=<?php echo toggleDirection($sortBy, $sortDir, 'id'); ?>">
                        #<?php echo sortIcon($sortBy, 'id', $sortDir); ?>
                    </a>
                </th>
                <th>
                    <a class="text-black text-decoration-none"
                       href="?sort=Email&dir=<?php echo toggleDirection($sortBy, $sortDir, 'Email'); ?>">
                        Email<?php echo sortIcon($sortBy, 'Email', $sortDir); ?>
                    </a>
                </th>
                <th>
                    <a class="text-black text-decoration-none"
                       href="?sort=First_Name&dir=<?php echo toggleDirection($sortBy, $sortDir, 'First_Name'); ?>">
                        Name<?php echo sortIcon($sortBy, 'First_Name', $sortDir); ?>
                    </a>
                </th>
                <th>
                    <a class="text-black text-decoration-none"
                       href="?sort=Department&dir=<?php echo toggleDirection($sortBy, $sortDir, 'Department'); ?>">
                        Department<?php echo sortIcon($sortBy, 'Department', $sortDir); ?>
                    </a>
                </th>
                <th>
                    <a class="text-black text-decoration-none"
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
                                Modify User
                            </button>
                        <?php endif; ?>
                        <?php if ($canDelete): ?>
                            <button type="button" class="btn btn-sm btn-danger delete-user"
                                    data-id="<?php echo htmlspecialchars($user['id']); ?>">
                                Remove User
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <!-- Pagination Controls -->
        <div class="container-fluid">
            <div class="row align-items-center g-3">
                <div class="col-12 col-sm-auto">
                    <div class="text-muted">
                        Showing <span id="currentPage">1</span> to <span
                                id="rowsPerPage">10</span> of
                        <span id="totalRows">100</span> entries
                    </div>
                </div>
                <div class="col-12 col-sm-auto ms-sm-auto">
                    <div class="d-flex align-items-center gap-2">
                        <button id="prevPage"
                                class="btn btn-outline-primary d-flex align-items-center gap-1">
                            <i class="bi bi-chevron-left"></i> Previous
                        </button>
                        <select id="rowsPerPageSelect" class="form-select" style="width: auto;">
                            <option value="10" selected>10</option>
                            <option value="20">20</option>
                            <option value="30">30</option>
                            <option value="50">50</option>
                        </select>
                        <button id="nextPage"
                                class="btn btn-outline-primary d-flex align-items-center gap-1">
                            Next <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-12">
                    <ul class="pagination justify-content-center" id="pagination"></ul>
                </div>
            </div>
        </div>
    </div>
    </div>

    <div class="mb-3">
        <button type="button" id="delete-selected" class="btn btn-danger" style="display: none;" disabled>
            Delete Selected
        </button>
    </div>
</div>

<!-- Modal for Creating a new user -->
<div class="modal fade" id="createUserModal" tabindex="-1" aria-labelledby="createUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createUserModalLabel">Create User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="createUserForm" method="POST" action="create_user.php">
                    <!-- Form fields for add user -->
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" name="email" id="email" class="form-control shadow-sm" required>
                        </div>
                        <div class="col-md-6">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" name="password" id="password" class="form-control shadow-sm" required>
                        </div>
                        <div class="col-md-6">
                            <label for="first_name" class="form-label">First Name</label>
                            <input type="text" name="first_name" id="first_name" class="form-control shadow-sm" required>
                        </div>
                        <div class="col-md-6">
                            <label for="last_name" class="form-label">Last Name</label>
                            <input type="text" name="last_name" id="last_name" class="form-control shadow-sm" required>
                        </div>
                        <div class="col-12">
                            <label for="modal_department" class="form-label">Department</label>
                            <select name="department" id="modal_department" class="form-select shadow-sm" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $code => $name): ?>
                                    <option value="<?php echo htmlspecialchars($code); ?>">
                                        <?php echo htmlspecialchars($name['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="custom">Custom Department</option>
                            </select>
                            <input type="text" id="modal_custom_department" name="custom_department"
                                   class="form-control mt-2 shadow-sm" style="display: none;" placeholder="Enter custom department">
                        </div>
                        
                        <!-- Role assignment fields - improved layout -->
                        <div class="col-12">
                            <fieldset class="mt-2">
                                <legend class="fs-5">Assign Roles <span class="text-danger">*</span></legend>
                                <div class="text-muted mb-2 small">At least one role must be selected</div>
                                <div class="role-checkbox-container">
                                    <?php
                                    $stmt = $pdo->prepare("SELECT * FROM roles WHERE is_disabled = 0 ORDER BY role_name");
                                    $stmt->execute();
                                    $modal_roles = $stmt->fetchAll();
                                    foreach ($modal_roles as $role): ?>
                                        <div class="form-check form-check-inline mb-2">
                                            <input type="checkbox" name="roles[]" value="<?php echo $role['id']; ?>"
                                                   id="modal_role_<?php echo $role['id']; ?>"
                                                   class="form-check-input modal-role-checkbox">
                                            <label for="modal_role_<?php echo $role['id']; ?>" class="form-check-label">
                                                <?php echo htmlspecialchars($role['role_name']); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="invalid-feedback" id="roles-error">Please select at least one role</div>
                            </fieldset>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Confirm Delete Modal -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel"
     aria-hidden="true">
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
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editUserForm" action="update_user.php" method="post">
                    <input type="hidden" id="editUserID" name="user_id">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label for="editEmail" class="form-label">Email</label>
                            <input type="email" class="form-control shadow-sm" id="editEmail" name="email">
                        </div>
                        <div class="col-md-6">
                            <label for="editFirstName" class="form-label">First Name</label>
                            <input type="text" class="form-control shadow-sm" id="editFirstName" name="first_name">
                        </div>
                        <div class="col-md-6">
                            <label for="editLastName" class="form-label">Last Name</label>
                            <input type="text" class="form-control shadow-sm" id="editLastName" name="last_name">
                        </div>
                        <div class="col-md-12">
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
                        
                        <!-- Added role checkboxes to edit modal -->
                        <div class="col-12">
                            <fieldset class="mt-2">
                                <legend class="fs-5">Assign Roles <span class="text-danger">*</span></legend>
                                <div class="text-muted mb-2 small">At least one role must be selected</div>
                                <div class="role-checkbox-container" id="editRolesContainer">
                                    <?php
                                    $stmt = $pdo->prepare("SELECT * FROM roles WHERE is_disabled = 0 ORDER BY role_name");
                                    $stmt->execute();
                                    $edit_roles = $stmt->fetchAll();
                                    foreach ($edit_roles as $role): ?>
                                        <div class="form-check form-check-inline mb-2">
                                            <input type="checkbox" name="edit_roles[]" value="<?php echo $role['id']; ?>"
                                                   id="edit_role_<?php echo $role['id']; ?>"
                                                   class="form-check-input edit-role-checkbox">
                                            <label for="edit_role_<?php echo $role['id']; ?>" class="form-check-label">
                                                <?php echo htmlspecialchars($role['role_name']); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="invalid-feedback" id="edit-roles-error">Please select at least one role</div>
                            </fieldset>
                        </div>
                        
                        <div class="col-md-12">
                            <label for="editPassword" class="form-label">
                                Change Password <span class="text-muted small">(Leave blank to keep current)</span>
                            </label>
                            <input type="password" class="form-control shadow-sm" id="editPassword" name="password">
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Include your pagination and user management JS files -->
<script type="text/javascript" src="<?php echo BASE_URL; ?>src/control/js/pagination.js" defer></script>
<script type="text/javascript" src="<?php echo BASE_URL; ?>src/control/js/user_management.js" defer></script>

</body>
</html>
