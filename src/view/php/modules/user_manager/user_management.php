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

// Check view permission
if (!$rbac->hasPrivilege('User Management', 'View')) {
    header("Location: " . BASE_URL . "src/view/php/general/access_denied.php");
    exit();
}

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
    // Handle error silently
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

// Modify the query to include department and search filters
$query = "SELECT u.id, u.email, u.first_name, u.last_name, u.status as Status FROM users u";

// Add department filter if selected
if (isset($_GET['department']) && $_GET['department'] !== 'all') {
    $query .= " JOIN user_departments ud ON u.id = ud.user_id WHERE ud.department_id = :department";
    $whereUsed = true;
} else {
    $query .= " WHERE 1=1";
    $whereUsed = true;
}

// Add search filter if provided
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $query .= " AND (u.email LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search)";
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

    /**
     * Check if user has specific privilege for a module
     */
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

    /**
     * Check if user can delete target user
     */
    public function canDeleteUser(int $targetUserId): bool
    {
        try {
            // First check if user has delete privilege
            if (!$this->hasPrivilege('User Management', 'Delete')) {
                return false;
            }

            // Get target user's roles
            $targetRoles = $this->getUserRoles($targetUserId);
            $currentUserRoles = $this->userRoles;

            // Get role hierarchies from database
            $roleHierarchy = $this->getRoleHierarchy();

            // Check if current user's role level is higher than target user's
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

    /**
     * Get user's roles
     */
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

    /**
     * Check if user can perform bulk delete
     */
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
}

// Initialize RBAC
$rbac = new RBACManager($pdo, $currentUserRoles);

// Check view permission
if (!$rbac->hasPrivilege('User Management', 'View')) {
    header("Location: ../../../../../public/index.php");
    exit();
}

// Check edit permission
$canEdit = $rbac->hasPrivilege('User Management', 'Edit');

// Check delete permission
$canDelete = $rbac->hasPrivilege('User Management', 'Delete');

// In your HTML/PHP view:
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

        .container-fluid {
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

    <!-- Modal for adding a new user -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">

                <div class="col-md-4 d-flex justify-content-end">
                    <?php if ($canCreate): ?>
                        <button type="button" class="btn btn-primary me-2" data-bs-toggle="modal"
                                data-bs-target="#addUserModal">
                            Add New User
                        </button>
                    <?php endif; ?>
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
                                        <?php echo htmlspecialchars($name['name']); ?>
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
    <!--FILTER SEARCH AND ADD USER BUTTON-->
    <div class="row mb-3">
        <div class="col-md-8">
            <form class="d-flex" method="GET">
                <!--Filter Department/Search-->
                <select name="department" class="form-select me-2 department-filter">
                    <option value="all">All Departments</option>
                    <?php foreach ($departments as $code => $name): ?>
                        <option value="<?php echo htmlspecialchars($code); ?>"
                            <?php echo (isset($_GET['department']) && $_GET['department'] === $code) ? 'selected' : ''; ?>>
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
                Add New User
            </button>
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
                        <input type="checkbox" class="select-row" value="<?php echo $user['id']; ?>">
                    </td>
                    <td><?php echo htmlspecialchars($user['id']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                    <td><?php
                        // Get user departments from junction table
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
                        ?></td>
                    <td><?php echo htmlspecialchars($user['Status'] ?? ''); ?></td>
                    <td>
                        <?php
                        // Fetch roles for each user.
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
                            <button type="button"
                                    class="btn btn-sm btn-warning btn-edit"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editUserModal"
                                    data-id="<?php echo htmlspecialchars($user['id']); ?>"
                                    data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                    data-first-name="<?php echo htmlspecialchars($user['first_name']); ?>"
                                    data-last-name="<?php echo htmlspecialchars($user['last_name']); ?>"
                                    data-department="<?php echo htmlspecialchars($userDeptIds[0] ?? ''); ?>">
                                Edit
                            </button>
                        <?php endif; ?>
                        <?php if ($canDelete): ?>
                            <button type="button" class="btn btn-sm btn-danger delete-user"
                                    data-id="<?php echo htmlspecialchars($user['id']); ?>">
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


<script>
    // Add this at the beginning of your $(document).ready function
    function showAlert(type, message) {
        const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
        $("#alertMessage").html(alertHtml).fadeIn();

        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            $("#alertMessage .alert").fadeOut(() => $(this).remove());
        }, 5000);
    }

    $(document).ready(function () {
        // Toggle the custom department input based on the selection
        $('#modal_department').on('change', function () {
            if ($(this).val() === 'custom') {
                $('#modal_custom_department').show().attr('required', true);
            } else {
                $('#modal_custom_department').hide().attr('required', false);
            }
        });

        // Handle form submission via AJAX
        $('#addUserForm').on('submit', function (e) {
            e.preventDefault();
            <?php if (!$canCreate): ?>
            showAlert('danger', 'You do not have permission to create users');
            return false;
            <?php endif; ?>
            // Get the action URL from the form
            var actionUrl = $(this).attr('action');
            console.log("Submitting to URL:", actionUrl);

            $.ajax({
                url: actionUrl, // The URL from the form's action attribute
                type: 'POST',
                data: $(this).serialize(), // Serialize the form data
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        // Hide the modal and refresh or update UI as needed
                        $('#addUserModal').modal('hide');
                        location.reload();
                    } else {
                        alert("Error: " + response.message);
                    }
                },
                error: function (xhr, status, error) {
                    // Log the raw response for debugging
                    console.log("Response Text:", xhr.responseText);
                    alert('An error occurred: ' + error);
                }
            });
        });

        $('.delete-user').on('click', function () {
            const userId = $(this).data("id");
            const row = $(this).closest('tr');

            if (confirm("Are you sure you want to archive this user?")) {
                $.ajax({
                    type: "POST",
                    url: "delete_user.php",
                    data: {user_id: userId},
                    dataType: 'json',
                    success: function (response) {
                        if (response.success) {
                            row.fadeOut(400, function () {
                                $(this).remove();
                            });
                            showAlert('success', response.message);
                        } else {
                            showAlert('danger', response.message || "Failed to archive user");
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error("Error details:", xhr.responseText);
                        showAlert('danger', "An error occurred while processing your request");
                    }
                });
            }
        });

        // Bulk delete handler
        $("#delete-selected").click(function () {
            const selected = $(".select-row:checked").map(function () {
                return $(this).val();
            }).get();

            if (selected.length === 0) {
                showAlert('warning', 'Please select users to archive.');
                return;
            }

            if (confirm(`Are you sure you want to archive ${selected.length} selected user(s)?`)) {
                $.ajax({
                    type: "POST",
                    url: "delete_user.php",
                    data: {user_ids: selected},
                    dataType: 'json',
                    success: function (response) {
                        if (response.success) {
                            selected.forEach(id => {
                                $(`tr[data-user-id="${id}"]`).fadeOut(400, function () {
                                    $(this).remove();
                                });
                            });
                            $("#select-all").prop('checked', false);
                            $("#delete-selected").prop('disabled', true).hide();
                            showAlert('success', response.message);
                        } else {
                            showAlert('danger', response.message || "Failed to archive users");
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error("Error details:", {xhr, status, error});
                        showAlert('danger', "An error occurred while processing your request");
                    }
                });
            }
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

        // Update the edit modal handler
        $('#editUserModal').on('show.bs.modal', function(event) {
            var button = $(event.relatedTarget);

            // Extract data
            var userId = button.data('id');
            var email = button.data('email');
            var firstName = button.data('first-name');
            var lastName = button.data('last-name');
            var department = button.data('department');

            // Log for debugging
            console.log("Loading user data:", {userId, email, firstName, lastName, department});

            // Populate fields
            var modal = $(this);
            modal.find('#editUserID').val(userId);
            modal.find('#editEmail').val(email);
            modal.find('#editFirstName').val(firstName);
            modal.find('#editLastName').val(lastName);
            modal.find('#editDepartment').val(department);
        });

// Update form submission handler
        $("#editUserForm").on("submit", function(e) {
            e.preventDefault();

            var submitButton = $(this).find('button[type="submit"]');
            submitButton.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...');

            $.ajax({
                type: "POST",
                url: "update_user.php",
                data: $(this).serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Update table row only if there were changes
                        if (response.data.hasChanges) {
                            var userId = $("#editUserID").val();
                            var row = $("button.btn-edit[data-id='" + userId + "']").closest('tr');

                            row.find('td:eq(2)').text(response.data.email);
                            row.find('td:eq(3)').text(response.data.first_name + ' ' + response.data.last_name);
                            if (response.data.department) {
                                row.find('td:eq(4)').text(response.data.department);
                            }

                            var editButton = row.find('.btn-edit');
                            editButton.data('email', response.data.email);
                            editButton.data('first-name', response.data.first_name);
                            editButton.data('last-name', response.data.last_name);
                            editButton.data('department', response.data.department);
                        }

                        $("#editUserModal").modal('hide');
                        showAlert('success', response.message);
                    } else {
                        showAlert('warning', response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX Error:", xhr.responseText);
                    showAlert('danger', 'Error updating user: ' + error);
                },
                complete: function() {
                    submitButton.prop('disabled', false).text('Save Changes');
                }
            });
        });
    });

</script>

<script type="text/javascript" src="<?php echo BASE_URL; ?>src/control/js/pagination.js" defer></script>
</body>
</html>
