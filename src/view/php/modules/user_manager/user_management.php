<?php

declare(strict_types=1);
require_once '../../../../../config/ims-tmdd.php';
session_start();
include '../../general/header.php';
include '../../general/sidebar.php';

// Enable error reporting for debugging (disable in production)
ini_set('display_errors', '1');
error_reporting(E_ALL);

// 1) Auth guard
$userId = $_SESSION['user_id'] ?? null;
if (!is_int($userId) && !ctype_digit((string)$userId)) {
    header("Location: " . BASE_URL . "index.php");
    exit();
}
$userId = (int)$userId;

// 2) Init RBAC & enforce "View"
$rbac = new RBACService($pdo, $_SESSION['user_id']);
$rbac->requirePrivilege('User Management', 'View');

// 3) Button flags
$canCreate = $rbac->hasPrivilege('User Management', 'Create');
$canModify = $rbac->hasPrivilege('User Management', 'Modify');
$canDelete = $rbac->hasPrivilege('User Management', 'Remove');
$canTrack = $rbac->hasPrivilege('User Management', 'Track');

// 4) Fetch departments
$departments = [];
try {
    $stmt = $pdo->query("
        SELECT id, department_name, abbreviation
          FROM departments
         WHERE is_disabled = 0
         ORDER BY department_name
    ");
    while ($d = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $departments[$d['id']] = $d;
    }
} catch (PDOException $e) {
    error_log('Error fetching departments: ' . $e->getMessage());
}

// Helpers
function getUserDepartments(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT DISTINCT udr.department_id, d.department_name, d.abbreviation 
         FROM user_department_roles udr
         JOIN departments d ON udr.department_id = d.id
         WHERE udr.user_id = ?'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function toggleDirection(string $currentSort, string $currentDir, string $col): string
{
    return $currentSort === $col
        ? ($currentDir === 'asc' ? 'desc' : 'asc')
        : 'asc';
}

function sortIcon(string $currentSort, string $col, string $dir): string
{
    if ($currentSort === $col) {
        return $dir === 'asc'
            ? ' <i class="bi bi-caret-up-fill"></i>'
            : ' <i class="bi bi-caret-down-fill"></i>';
    }
    return '';
}

// 5) Build filters & sort
$sortMap = [
    'id' => 'u.id',
    'Email' => 'u.email',
    'First_Name' => 'u.first_name',
    'Last_Name' => 'u.last_name',
    'Department' => 'd.department_name',
    'Status' => 'u.status',
];

$sortBy = $_GET['sort'] ?? 'id';
if (!isset($sortMap[$sortBy])) {
    $sortBy = 'id';
}

$sortDir = ($_GET['dir'] ?? '') === 'desc' ? 'desc' : 'asc';

$hasDeptFilter = isset($_GET['department']) && $_GET['department'] !== 'all';
$hasSearchFilter = !empty(trim((string)($_GET['search'] ?? '')));

// Base SQL
$sql = "
SELECT
  u.id,
  u.email,
  u.username,
  u.first_name,
  u.last_name,
  u.status AS Status,
  u.is_disabled,
  u.profile_pic_path,
  GROUP_CONCAT(DISTINCT d.department_name ORDER BY d.department_name) AS departments,
  GROUP_CONCAT(DISTINCT r.role_name ORDER BY r.role_name) AS roles
FROM users u
LEFT JOIN user_department_roles udr
  ON u.id = udr.user_id
LEFT JOIN departments d
  ON udr.department_id = d.id
LEFT JOIN roles r
  ON udr.role_id = r.id
WHERE u.is_disabled = 0
GROUP BY u.id, u.email, u.username, u.first_name, u.last_name, u.status, u.is_disabled
";

if ($hasDeptFilter) {
    $sql .= " HAVING departments LIKE :department";
}
if ($hasSearchFilter) {
    $sql .= $hasDeptFilter ? " AND (" : " HAVING (";
    $sql .= "
        u.email           LIKE :search
     OR u.username        LIKE :search
     OR u.first_name      LIKE :search
     OR u.last_name       LIKE :search
     OR departments       LIKE :search
     OR roles             LIKE :search
    )";
}

$sql .= " ORDER BY {$sortMap[$sortBy]} $sortDir";

try {
    $stmt = $pdo->prepare($sql);

    if ($hasDeptFilter) {
        $deptId = filter_var($_GET['department'], FILTER_VALIDATE_INT);
        if ($deptId === false) {
            die('Invalid department ID.');
        }
        $stmt->bindValue(':department', $deptId, PDO::PARAM_INT);
    }
    if ($hasSearchFilter) {
        $like = '%' . trim((string)$_GET['search']) . '%';
        $stmt->bindValue(':search', $like, PDO::PARAM_STR);
    }

    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    error_log('User fetch error: ' . $e->getMessage());
    die('An error occurred while fetching users.');
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>src/view/styles/css/pagination.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>src/view/styles/css/user_roles_management.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>src/view/styles/css/user_management.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Bootstrap 5 bundle (includes Popper & the native Modal/Data API) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo BASE_URL; ?>src/control/js/user_management.js" defer></script>
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <title>Manage Users</title>
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
</head>

<body>
    <div class="main-content container-fluid">
        <header>
            <h1>USER MANAGER</h1>
        </header>

        <div class="filters-container d-flex align-items-end mb-3">
            <div class="search-filter me-3">
                <label for="search-filters" class="form-label">Search Users</label>
                <input type="text" id="search-filters" name="search" class="form-control"
                    placeholder="Search…"
                    value="<?php echo htmlspecialchars($_GET['search'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="filter-container me-auto">
                <label for="department-filter" class="form-label">Filter by Department</label>
                <select id="department-filter" name="department" class="form-select" style="width: 250px;">
                    <option value="all">All Departments</option>
                    <?php
                    // Fetch all departments directly for the filter dropdown, show ALL regardless of is_disabled
                    try {
                        $deptStmt = $pdo->query("SELECT department_name FROM departments ORDER BY department_name");
                        $allDepartments = $deptStmt->fetchAll(PDO::FETCH_COLUMN);
                        foreach ($allDepartments as $deptName) {
                            echo '<option value="' . htmlspecialchars($deptName) . '"';
                            if ((isset($_GET['department']) && $_GET['department'] === $deptName)) {
                                echo ' selected';
                            }
                            echo '>' . htmlspecialchars($deptName) . '</option>';
                        }
                    } catch (PDOException $e) {
                        // fallback: empty
                    }
                    ?>
                </select>
            </div>

            <?php if ($canCreate): ?>
                <button id="create-btn" class="btn btn-primary me-2">
                    Create New User
                </button>
            <?php endif; ?>


            <?php if ($canDelete): ?>
                <!-- Bulk remove button, hidden until >=2 checked -->
                <button id="delete-selected"
                    class="btn btn-danger me-2"
                    style="display:none;"
                    disabled>
                    Remove Selected
                </button>
            <?php endif; ?>
        </div>

        <div class="table-responsive" id="table">
            <table class="table table-striped table-hover" id="umTable">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="select-all"></th>
                        <th>
                            <a href="?sort=id&dir=<?php echo toggleDirection($sortBy, $sortDir, 'id'); ?>">
                                #<?php echo sortIcon($sortBy, 'id', $sortDir); ?>
                            </a>
                        </th>
                        <th>
                            <a href="?sort=Email&dir=<?php echo toggleDirection($sortBy, $sortDir, 'Email'); ?>">
                                Email<?php echo sortIcon($sortBy, 'Email', $sortDir); ?>
                            </a>
                        </th>
                        <th>
                            <a>Profile Picture</a>
                        </th>

                        <th>
                            <a href="?sort=First_Name&dir=<?php echo toggleDirection($sortBy, $sortDir, 'First_Name'); ?>">
                                Username<?php echo sortIcon($sortBy, 'First_Name', $sortDir); ?>
                            </a>
                        </th>
                        <th>
                            <a href="?sort=Department&dir=<?php echo toggleDirection($sortBy, $sortDir, 'Department'); ?>">
                                Department<?php echo sortIcon($sortBy, 'Department', $sortDir); ?>
                            </a>
                        </th>
                        <th>
                            <a href="?sort=Status&dir=<?php echo toggleDirection($sortBy, $sortDir, 'Status'); ?>">
                                Status<?php echo sortIcon($sortBy, 'Status', $sortDir); ?>
                            </a>
                        </th>
                        <th>Roles</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted">No users found.</td>
                        </tr>
                        <?php else: foreach ($users as $u): ?>
                            <tr>
                                <td>
                                    <input type="checkbox"
                                        class="select-row"
                                        value="<?= htmlspecialchars((string)$u['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                </td>
                                <td><?= htmlspecialchars((string)$u['id'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <img 
                                        src="<?= !empty($u['profile_pic_path']) 
                                            ? '../../../../../public/' . htmlspecialchars($u['profile_pic_path'], ENT_QUOTES, 'UTF-8') 
                                            : '../../../../../public/assets/img/default_profile.jpg'; ?>" 
                                        alt="Profile Picture"
                                        class="profile-picture"
                                        style="width: 40px; height: 40px; object-fit: cover; border-radius: 50%;">
                                </td>

                                <td><?= htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <?php
                                    $depts = getUserDepartments($pdo, (int)$u['id']);
                                    if ($depts) {
                                        $deptNames = array_map(function($dept) {
                                            return $dept['abbreviation'] ?: $dept['department_name'];
                                        }, $depts);
                                        echo htmlspecialchars(implode(', ', $deptNames), ENT_QUOTES, 'UTF-8');
                                    } else {
                                        echo 'Not assigned';
                                    }
                                    ?>
                                </td>
                                <td><?= htmlspecialchars($u['Status'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <?php
                                    $rS = $pdo->prepare("
                    SELECT DISTINCT r.role_name
                      FROM user_department_roles ur
                      JOIN roles r ON r.id=ur.role_id AND r.is_disabled=0
                     WHERE ur.user_id=?
                  ");
                                    $rS->execute([(int)$u['id']]);
                                    echo htmlspecialchars(
                                        implode(', ', $rS->fetchAll(PDO::FETCH_COLUMN)),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>
                                </td>
                                <td>
                                    <?php if ($canModify): ?>
                                        <button class="btn btn-sm btn-outline-primary edit-btn"
                                            data-id="<?= htmlspecialchars((string)$u['id'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-email="<?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-username="<?= htmlspecialchars($u['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                            data-first-name="<?= htmlspecialchars($u['first_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-last-name="<?= htmlspecialchars($u['last_name'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($canDelete): ?>
                                        <button class="btn btn-sm btn-outline-danger delete-btn"
                                            data-id="<?= htmlspecialchars((string)$u['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                    <?php endforeach;
                    endif; ?>
                </tbody>
            </table>
            <!-- Pagination Controls -->
            <div class="container-fluid">
                <div class="row align-items-center g-3">
                    <div class="col-12 col-sm-auto">
                        <div class="text-muted">
                            <?php $totalUsers = count($users); ?>
                            <input type="hidden" id="total-users" value="<?= $totalUsers ?>">
                            Showing <span id="currentPage">1</span> to <span id="rowsPerPage"><?= min($totalUsers, 10) ?></span> of <span id="totalRows"><?= $totalUsers ?></span> entries
                        </div>
                    </div>
                    <div class="col-12 col-sm-auto ms-sm-auto">
                        <div class="d-flex align-items-center gap-2">
                            <button id="prevPage" class="btn btn-outline-primary d-none" <?= $totalUsers <= 10 ? 'style="display:none !important;"' : '' ?>>
                                <i class="bi bi-chevron-left"></i> Previous
                            </button>
                            <select id="rowsPerPageSelect" class="form-select" style="width: auto;">
                                <option value="10" selected>10</option>
                                <option value="20">20</option>
                                <option value="30">30</option>
                                <option value="50">50</option>
                            </select>
                            <button id="nextPage" class="btn btn-outline-primary d-none" <?= $totalUsers <= 10 ? 'style="display:none !important;"' : '' ?>>
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

    <?php include '../../general/footer.php'; ?>

    <!-- Create User Modal -->
    <div class="modal fade" id="createUserModal" tabindex="-1" aria-labelledby="createUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lower">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createUserModalLabel">Create User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="createUserForm" method="POST" action="create_user.php">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" name="email" id="email" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" name="username" id="username" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <input type="password" name="password" id="password" class="form-control" required>
                                    <button class="btn btn-outline-secondary toggle-password" type="button">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div class="password-strength d-none mt-2">
                                    <div class="progress">
                                        <div class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <small class="strength-text mt-1">Password strength</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" name="first_name" id="first_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" name="last_name" id="last_name" class="form-control" required>
                            </div>
                            <div class="col-md-12">
                                <label for="modal_department" class="form-label">Department <span class="text-danger">*</span></label>
                                <select name="department" id="modal_department" class="form-select" style="width: 100%;">
                                    <option value="">Select Department</option>
                                    <?php foreach ($departments as $code => $d): ?>
                                        <option value="<?= $code ?>"><?= htmlspecialchars($d['department_name']) ?></option>
                                    <?php endforeach; ?>
                                </select> <!-- No 'All Departments' and no 'Custom Department' -->
                                <small class="form-text text-muted">Select one or more departments (required)</small>
                                <input type="text" id="modal_custom_department" name="custom_department"
                                    class="form-control mt-2" style="display:none;"
                                    placeholder="Enter custom department">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Currently Assigned Departments</label>
                                <div id="createAssignedDepartmentsList" class="border rounded p-2 mb-2">
                                    <!-- Department badges will be added here dynamically -->
                                </div>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Assigned Departments Table</label>
                                <div class="table-responsive department-table-container">
                                    <table class="table table-striped table-hover" id="createAssignedDepartmentsTable">
                                        <thead>
                                            <tr>
                                                <th>Department Name</th>
                                                <th class="text-end" style="width: 60px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Department rows will be added here dynamically -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Create User</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lower">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editUserForm" method="POST" action="update_user.php">
                        <input type="hidden" name="user_id" id="editUserID">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label for="editEmail" class="form-label">Email</label>
                                <input type="email" name="email" id="editEmail" class="form-control">
                            </div>
                            <div class="col-md-12">
                                <label for="editUsername" class="form-label">Username</label>
                                <input type="text" name="username" id="editUsername" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label for="editFirstName" class="form-label">First Name</label>
                                <input type="text" name="first_name" id="editFirstName" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label for="editLastName" class="form-label">Last Name</label>
                                <input type="text" name="last_name" id="editLastName" class="form-control">
                            </div>
                            <div class="col-md-12">
                                <label for="editPassword" class="form-label">
                                    Change Password <span class="small text-muted">(Leave blank to keep current)</span>
                                </label>
                                <input type="password" name="password" id="editPassword" class="form-control">
                            </div>
                            <div class="col-md-12">
                                <label for="editDepartments" class="form-label">Departments <span class="text-danger">*</span></label>
                                <select name="departments[]" id="editDepartments" class="form-select">
                                    <option value="">Select departments</option>
                                    <?php foreach ($departments as $dept_id => $d): ?>
                                        <option value="<?= $dept_id ?>"><?= htmlspecialchars($d['department_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text text-muted">Select one or more departments (required)</small>
                            </div>
                            <div class="col-md-12 mt-2">
                                <label class="form-label">Currently Assigned Departments</label>
                                <div id="assignedDepartmentsList" class="border rounded p-2 mb-2">
                                    <!-- Department badges will be added here dynamically -->
                                </div>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Assigned Departments Table</label>
                                <div class="table-responsive department-table-container">
                                    <table class="table table-striped table-hover" id="assignedDepartmentsTable">
                                        <thead>
                                            <tr>
                                                <th>Department Name</th>
                                                <th class="text-end" style="width: 60px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Department rows will be added here dynamically -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirm Delete Modal -->
    <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lower">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmDeleteModalLabel">Confirm Remove</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="confirmDeleteMessage"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="confirmDeleteButton" class="btn btn-danger">Delete</button>
                </div>
            </div>
        </div>
    </div>
<script>
$(document).ready(function() {
    // Initialize Select2 for department filter
    $('#department-filter').select2({
        placeholder: 'All Departments',
        allowClear: true,
        width: 'resolve'
    });
    // Preserve selection on reload
    var selectedDept = '<?php echo isset($_GET['department']) ? htmlspecialchars($_GET['department'], ENT_QUOTES, 'UTF-8') : 'all'; ?>';
    if (selectedDept) {
        $('#department-filter').val(selectedDept).trigger('change');
    }
});
</script>
<script>
$(document).ready(function() {
    // Initialize Select2 for department filter (already present)
    $('#department-filter').select2({
        placeholder: 'All Departments',
        allowClear: true,
        width: 'resolve'
    });
    var selectedDept = '<?php echo isset($_GET['department']) ? htmlspecialchars($_GET['department'], ENT_QUOTES, 'UTF-8') : 'all'; ?>';
    if (selectedDept) {
        $('#department-filter').val(selectedDept).trigger('change');
    }
    // Initialize Select2 for modal department dropdown
    $('#modal_department').select2({
        dropdownParent: $('#createUserModal'),
        placeholder: 'Select Department',
        allowClear: true,
        width: '100%'
    });
    
    // Initialize Select2 for edit department dropdown
    $('#editDepartments').select2({
        dropdownParent: $('#editUserModal'),
        placeholder: 'Select Department',
        allowClear: true,
        width: '100%'
    });
});
</script>
</body>

</html>