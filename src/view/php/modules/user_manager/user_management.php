<?php

declare(strict_types=1);
require_once '../../../../../config/ims-tmdd.php';
session_start();
include '../../general/header.php';
include '../../general/sidebar.php';
include '../../general/footer.php';

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

// 5) Build filters & sort
$sortMap = [
    'id' => 'u.id',
    'Email' => 'u.email',
    'First_Name' => 'u.first_name',
    'Last_Name' => 'u.last_name',
    'Department' => 'd.department_name',
    'Status' => 'u.status',
];

// Default sorting
$sortBy = 'id';
$sortDir = 'asc';

// Base SQL - simplified to load all users without server-side filtering
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
  GROUP_CONCAT(DISTINCT d.abbreviation ORDER BY d.abbreviation) AS dept_abbreviations,
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
ORDER BY u.id DESC
";

try {
    $stmt = $pdo->prepare($sql);
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
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>src/view/styles/css/user_module.css">
    <!-- Bootstrap 5 bundle (includes Popper & the native Modal/Data API) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <!-- User Management JS -->
    <script src="<?php echo BASE_URL; ?>src/control/js/user_management.js" defer></script>
    <!-- Pagination JS -->
    <script src="<?php echo BASE_URL; ?>src/control/js/pagination.js" defer></script>

    <title>Manage Users</title>
    <style>
        /* Fix Select2 dropdown positioning in modals */
        .select2-container--default {
            z-index: 9999 !important;
        }

        .modal-content {
            overflow: hidden;
            /* Prevent content from showing through */
        }

        .modal-footer {
            border-top: 1px solid #dee2e6;
            background-color: #fff;
            margin-top: 1rem;
            padding: 1rem;
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 0.5rem;
        }

        /* Fix for Department select dropdown in modals */
        .select2-dropdown {
            z-index: 9999 !important;
            /* Ensure dropdown appears over other elements */
        }

        .select2-container--open .select2-dropdown {
            margin-top: 2px;
        }

        /* Additional fixes from user_roles_management.php */
        .select2-container {
            width: 100% !important;
        }

        body.modal-open {
            overflow: hidden;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.4);
        }

        /* Toast notification styles */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
        }

        .toast {
            min-width: 300px;
        }

        /* Pagination styles */
        .pagination {
            display: flex;
            justify-content: center;
            margin: 20px 0;
        }

        .pagination .page-item {
            margin: 0 2px;
        }

        .pagination .page-item.active .page-link {
            background-color: #0d6efd;
            border-color: #0d6efd;
            color: white;
        }

        .pagination .page-link {
            color: #0d6efd;
            border: 1px solid #dee2e6;
            padding: 0.375rem 0.75rem;
            border-radius: 0.25rem;
            text-decoration: none;
        }

        .pagination .page-link:hover {
            background-color: #e9ecef;
        }

        .pagination .page-item.disabled .page-link {
            color: #6c757d;
            pointer-events: none;
            background-color: #fff;
        }
    </style>
</head>

<body>
    <div class="main-content container-fluid">
        <header>
            <h1>USER MANAGER</h1>
        </header>

        <div class="filters-container">
            <?php if ($canCreate): ?>
                <button type="button" id="create-btn" class="btn btn-dark">
                    <i class="bi bi-plus-lg"></i> Create New User
                </button>
            <?php endif; ?>
            <!--Filter -->
            <div class="filter-container">
                <!-- <label for="department-filter">FILTER BY DEPARTMENT</label>
                <select id="department-filter" name="department" autocomplete="off">
                    <option value="all">All Departments</option>
                    <?php
                    // Fetch all departments directly for the filter dropdown, show ALL regardless of is_disabled
                    try {
                        // Fetch both acronym and department_name
                        $deptStmt = $pdo->query("SELECT department_name, abbreviation FROM departments WHERE is_disabled = 0 ORDER BY department_name");
                        $allDepartments = $deptStmt->fetchAll(PDO::FETCH_ASSOC);

                        foreach ($allDepartments as $dept) {
                            $name = htmlspecialchars($dept['department_name']);
                            $abbreviation = htmlspecialchars($dept['abbreviation']);
                            $label = "($abbreviation) $name";
                            echo '<option value="' . $name . '">' . $label . '</option>';
                        }
                    } catch (PDOException $e) {
                        // fallback: empty
                    }
                    ?>
                </select> -->
            </div>

            <!-- Buttons -->
            <div class="col-6 col-md-2 d-grid">
                <button type="submit" class="btn btn-dark"><i class="bi bi-funnel"></i> Filter</button>
            </div>

            <div class="col-6 col-md-2 d-grid">
                <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-secondary shadow-sm"><i class="bi bi-x-circle"></i> Clear</a>
            </div>

            <div class="action-buttons">
                <?php if ($rbac->hasPrivilege('User Management', 'Modify')): ?>
                    <a href="user_roles_management.php" class="btn btn-primary"> Manage Role Assignments</a>
                <?php endif; ?>
                <?php if ($canDelete): ?>
                    <!-- Bulk remove button, hidden until >=2 checked -->
                    <button type="button" id="delete-selected"
                        class="btn btn-danger"
                        style="display:none;"
                        disabled>
                        Remove Selected
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-responsive" id="table">
            <table class="table table-striped table-hover" id="umTable">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="select-all"></th>
                        <th>
                            <a href="#" class="sort-header" data-sort="id">
                                #<i class="bi bi-caret-up-fill sort-icon"></i>
                            </a>
                        </th>
                        <th>
                            <a href="#" class="sort-header" data-sort="email">
                                Email<i class="bi bi-caret-up-fill sort-icon"></i>
                            </a>
                        </th>
                        <th>
                            <a>Profile Picture</a>
                        </th>

                        <th>
                            <a href="#" class="sort-header" data-sort="username">
                                Username<i class="bi bi-caret-up-fill sort-icon"></i>
                            </a>
                        </th>
                        <th>
                            <a href="#" class="sort-header" data-sort="department">
                                Department<i class="bi bi-caret-up-fill sort-icon"></i>
                            </a>
                        </th>
                        <th>
                            <a href="#" class="sort-header" data-sort="status">
                                Status<i class="bi bi-caret-up-fill sort-icon"></i>
                            </a>
                        </th>
                        <th>Roles</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="umTableBody">
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted">No users found.</td>
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
                                        class="profile-picture">
                                </td>

                                <td><?= htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <?php
                                    $depts = getUserDepartments($pdo, (int)$u['id']);
                                    if ($depts) {
                                        $deptNames = array_map(function ($dept) {
                                            if (!empty($dept['abbreviation'])) {
                                                return htmlspecialchars($dept['department_name'] . ' (' . $dept['abbreviation'] . ')', ENT_QUOTES, 'UTF-8');
                                            } else {
                                                return htmlspecialchars($dept['department_name'], ENT_QUOTES, 'UTF-8');
                                            }
                                        }, $depts);
                                        $deptString = implode(', ', $deptNames);
                                        echo '<span title="' . $deptString . '">' . $deptString . '</span>';
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
                                        <button class="btn-outline-primary edit-btn"
                                            data-id="<?= htmlspecialchars((string)$u['id'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-email="<?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-username="<?= htmlspecialchars($u['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                            data-first-name="<?= htmlspecialchars($u['first_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-last-name="<?= htmlspecialchars($u['last_name'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($canDelete): ?>
                                        <button class="btn-outline-danger delete-btn"
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
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <ul class="pagination justify-content-center" id="pagination"></ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
                                <input type="email" name="email" id="email" class="form-control" required pattern="[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$" title="Please enter a valid email address with a domain (e.g. user@example.com)">
                                <small class="form-text text-muted">Email must include a domain (e.g. user@example.com)</small>
                                <div class="invalid-feedback" id="emailFeedback">
                                    Please enter a valid email address with a domain (e.g. user@example.com)
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" name="username" id="username" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <input type="password" name="password" id="password" class="form-control" autocomplete="new-password" placeholder="Leave a blank to keep current password" required>
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
                                        <?php
                                        // DEBUG dump
                                        echo '<!-- DEBUG: ';
                                        var_dump($d);
                                        echo ' -->';
                                        ?>
                                        <option value="<?= htmlspecialchars(strval($code)) ?>">
                                            (<?= htmlspecialchars($d['abbreviation']) ?>) <?= htmlspecialchars($d['department_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text text-muted">Select one or more departments (required)</small>
                                <input type="text" id="modal_custom_department" name="custom_department"
                                    class="form-control mt-2" style="display:none;"
                                    placeholder="Enter custom department">
                            </div>

                            <div class="col-md-12">
                                <label class="form-label">Assigned Departments Table</label>
                                <div class="department-table-container">
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
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="submitCreateUser">Create User</button>
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
                    <form
                        id="editUserForm"
                        method="POST"
                        action="update_user.php"
                        autocomplete="off">
                        <input type="hidden" name="user_id" id="editUserID">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label for="editEmail" class="form-label">Email</label>
                                <input type="email" name="email" id="editEmail" class="form-control" pattern="[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$" title="Please enter a valid email address with a domain (e.g. user@example.com)">
                                <small class="form-text text-muted">Email must include a domain (e.g. user@example.com)</small>
                                <div class="invalid-feedback" id="editEmailFeedback">
                                    Please enter a valid email address with a domain (e.g. user@example.com)
                                </div>
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
                                        <?php
                                        // DEBUG: Show full array content in HTML comment
                                        echo '<!-- DEBUG: ';
                                        var_dump($d);
                                        echo ' -->';
                                        ?>
                                        <option value="<?= htmlspecialchars(strval($dept_id)) ?>">
                                            (<?= htmlspecialchars($d['abbreviation']) ?>) <?= htmlspecialchars($d['department_name']) ?>
                                        </option>
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
                                <div class="department-table-container">
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
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="submitEditUser">Save Changes</button>
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
                    <h5 class="modal-title" id="confirmDeleteModalLabel">Confirm Remove</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="confirmDeleteMessage"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="confirmDeleteButton" class="btn btn-danger">Remove</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Initialize pagination
            if (typeof initPagination === 'function') {
                console.log("Initializing pagination for user management");
                initPagination({
                    tableId: 'umTableBody',
                    rowsPerPageSelectId: 'rowsPerPageSelect',
                    currentPageId: 'currentPage',
                    rowsPerPageId: 'rowsPerPage',
                    totalRowsId: 'totalRows',
                    prevPageId: 'prevPage',
                    nextPageId: 'nextPage',
                    paginationId: 'pagination'
                });
            }

            const originalUpdatePagination = window.updatePagination || function() {};

            // Function to check and update pagination visibility
            window.forcePaginationCheck = function() {
                const totalRows = window.filteredRows ? window.filteredRows.length : 0;
                const rowsPerPage = parseInt(document.getElementById('rowsPerPageSelect').value) || 10;
                const prevBtn = document.getElementById('prevPage');
                const nextBtn = document.getElementById('nextPage');
                const paginationEl = document.getElementById('pagination');

                // Hide pagination completely if all rows fit on one page
                if (totalRows <= rowsPerPage) {
                    if (prevBtn) prevBtn.style.cssText = 'display: none !important';
                    if (nextBtn) nextBtn.style.cssText = 'display: none !important';
                    if (paginationEl) paginationEl.style.cssText = 'display: none !important';
                } else {
                    // Show pagination but conditionally hide prev/next buttons
                    if (paginationEl) paginationEl.style.cssText = '';

                    if (prevBtn) {
                        if (window.paginationConfig && window.paginationConfig.currentPage <= 1) {
                            prevBtn.style.cssText = 'display: none !important';
                        } else {
                            prevBtn.style.cssText = '';
                        }
                    }

                    if (nextBtn) {
                        const totalPages = Math.ceil(totalRows / rowsPerPage);
                        if (window.paginationConfig && window.paginationConfig.currentPage >= totalPages) {
                            nextBtn.style.cssText = 'display: none !important';
                        } else {
                            nextBtn.style.cssText = '';
                        }
                    }
                }
            }

            window.updatePagination = function() {
                // Get all rows again in case the DOM was updated
                window.allRows = Array.from(document.querySelectorAll('#umTableBody tbody tr'));

                // If filtered rows is empty or not defined, use all rows
                if (!window.filteredRows || window.filteredRows.length === 0) {
                    window.filteredRows = window.allRows;
                }

                // Update total rows display
                const totalRowsEl = document.getElementById('totalRows');
                if (totalRowsEl) {
                    totalRowsEl.textContent = window.filteredRows.length;
                }

                // Call original updatePagination
                originalUpdatePagination();
                forcePaginationCheck();
            };

            // Call updatePagination immediately
            updatePagination();

            // Handle edit user form submission
            $('#submitEditUser').on('click', function() {
                const form = $('#editUserForm');
                const formData = new FormData(form[0]);

                // Validate email has domain
                const email = $('#editEmail').val();
                if (!validateEmail(email)) {
                    $('#editEmail').addClass('is-invalid');
                    return;
                } else {
                    $('#editEmail').removeClass('is-invalid');
                }

                // Check if departments are selected from the assigned departments table
                const departmentRows = $('#assignedDepartmentsTable tbody tr');
                if (departmentRows.length === 0) {
                    Toast.error('At least one department must be assigned');
                    return;
                }

                // Clear any existing department values to avoid duplicates
                formData.delete('departments[]');
                formData.delete('department');

                // Add all departments as array
                departmentRows.each(function(index) {
                    const deptId = $(this).data('department-id');
                    if (deptId) {
                        formData.append(`departments[${index}]`, deptId);
                        // Also set the first department as the primary one for compatibility
                        if (index === 0) {
                            formData.append('department', deptId);
                        }
                    }
                });

                $.ajax({
                    url: form.attr('action'),
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        try {
                            const result = typeof response === 'string' ? JSON.parse(response) : response;
                            if (result.success) {
                                Toast.success('User updated successfully');
                                $('#editUserModal').modal('hide');
                                // Use reloadUserTable function instead of page reload
                                if (typeof reloadUserTable === 'function') {
                                    reloadUserTable();
                                } else {
                                    // Fallback to page reload if function not available
                                    setTimeout(function() {
                                        window.location.reload();
                                    }, 1000);
                                }
                            } else {
                                Toast.error(result.message || 'Failed to update user');
                            }
                        } catch (e) {
                            // Handle non-JSON responses which might still indicate success
                            if (typeof response === 'string' && response.includes('success')) {
                                Toast.success('User updated successfully');
                                $('#editUserModal').modal('hide');
                                // Use reloadUserTable function instead of page reload
                                if (typeof reloadUserTable === 'function') {
                                    reloadUserTable();
                                } else {
                                    // Fallback to page reload if function not available
                                    setTimeout(function() {
                                        window.location.reload();
                                    }, 1000);
                                }
                            } else {
                                Toast.error('Error processing response');
                                console.error('Error parsing response:', e, response);
                            }
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("Error updating user:", error);
                        console.error("Response text:", xhr.responseText);

                        try {
                            // First try to extract JSON from the response if it contains HTML errors
                            let jsonStr = xhr.responseText;
                            if (jsonStr.includes('{') && jsonStr.includes('}')) {
                                jsonStr = jsonStr.substring(jsonStr.indexOf('{'), jsonStr.lastIndexOf('}') + 1);
                                const result = JSON.parse(jsonStr);
                                Toast.error(result.message || 'Failed to update user');
                            } else {
                                const result = JSON.parse(xhr.responseText);
                                Toast.error(result.message || 'Failed to update user');
                            }
                        } catch (e) {
                            // If there's a username error in the response text, extract and show it
                            if (xhr.responseText.includes('username is already taken')) {
                                Toast.error('Username is already taken. Please try a different username.');
                            } else {
                                Toast.error('Server error occurred. Please try again.');
                            }
                            console.error('Parse error:', e);
                        }
                    }
                });
            });
        });

        // Initialize selectedDepartments array for the global scope
        let selectedDepartments = [];

        // Email validation function
        function validateEmail(email) {
            const regex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
            return regex.test(email);
        }

        // Initialize Select2 for department filter with custom positioning
        $('#department-filter').select2({
            placeholder: 'All Departments',
            allowClear: true,
            minimumResultsForSearch: 5,
            dropdownParent: $('body') // Attach to body for proper z-index handling
        });

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

        // Add department selection handler for create user modal
        $('#modal_department').on('change', function() {
            const deptId = $(this).val();
            if (!deptId) return; // Skip if no department selected

            const deptName = $(this).find('option:selected').text();

            if (deptId && !selectedDepartments.some(d => d.id === deptId)) {
                selectedDepartments.push({
                    id: deptId,
                    name: deptName
                });
                updateDepartmentsDisplay();
            }

            // Reset selection
            $(this).val(null).trigger('change');
        });

        // Use event delegation for dynamically added elements
        $(document).on('click', '.edit-btn', function() {
            $(".modal-backdrop").hide();
            const userId = $(this).data('id');
            const email = $(this).data('email');
            const username = $(this).data('username');
            const firstName = $(this).data('first-name');
            const lastName = $(this).data('last-name');

            // Set values in form
            $('#editUserID').val(userId);
            $('#editEmail').val(email);
            $('#editUsername').val(username);
            $('#editFirstName').val(firstName);
            $('#editLastName').val(lastName);

            // Clear previous department selections
            selectedDepartments = [];
            updateEditDepartmentsDisplay();

            // Fetch user's departments
            $.ajax({
                url: 'get_user_departments.php',
                type: 'GET',
                data: {
                    user_id: userId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Ensure department ids are treated as integers
                        selectedDepartments = response.departments.map(dept => ({
                            id: parseInt(dept.id),
                            name: dept.name
                        }));
                        updateEditDepartmentsDisplay();
                    } else {
                        console.error("Failed to load departments:", response.message);
                        Toast.error(response.message || 'Failed to load user departments');
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Error fetching departments:", error);
                    Toast.error('Failed to load user departments. Please try again.');
                }
            });

            // Show the modal
            var editModal = document.getElementById('editUserModal');
            var modal = new bootstrap.Modal(editModal);
            modal.show();
        });

        // Add department selection handler for edit user modal
        $('#editDepartments').on('change', function() {
            const deptId = $(this).val();
            if (!deptId) return; // Skip if no department selected

            const deptName = $(this).find('option:selected').text();

            // Check if this department is already in the array to avoid duplicates
            if (deptId && !selectedDepartments.some(d => String(d.id) === String(deptId))) {
                // Add to the existing array instead of replacing it
                selectedDepartments.push({
                    id: deptId,
                    name: deptName
                });
                updateEditDepartmentsDisplay();
            }

            // Reset selection
            $(this).val(null).trigger('change');
        });

        // Function to update departments display table
        function updateDepartmentsDisplay() {
            // Update create user departments display
            const $table = $('#createAssignedDepartmentsTable tbody');

            $table.empty();

            selectedDepartments.forEach(function(dept) {
                // Add row to table
                $table.append(`
                        <tr data-department-id="${dept.id}">
                            <td>${dept.name}</td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-danger remove-dept" data-dept-id="${dept.id}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `);
            });

            // Add event handlers for removal buttons
            $('.remove-dept').on('click', function() {
                const deptId = $(this).data('dept-id');

                selectedDepartments = selectedDepartments.filter(d => String(d.id) !== String(deptId));
                updateDepartmentsDisplay();

            });
        }

        // Fix Select2 dropdown positioning in modals
        $('.modal').on('shown.bs.modal', function() {
            $(this).find('.select2-container').css('width', '100%');
            $(this).find('select').trigger('change.select2');
        });
        $('#editUserModal').on('shown.bs.modal', function() {
            // your existing Select2 width fix…
            $(this).find('.select2-container').css('width', '100%');
            $(this).find('select').trigger('change.select2');

            // CLEAR password field so browser autofill is removed
            $('#editPassword').val('');
        });

        // Sorting variables
        let currentSort = 'id';
        let currentSortDir = 'asc';

        // Client-side sorting function
        function sortTable(column) {
            // Update sort direction if clicking the same column
            if (column === currentSort) {
                currentSortDir = currentSortDir === 'asc' ? 'desc' : 'asc';
            } else {
                currentSort = column;
                currentSortDir = 'asc';
            }

            // Update sort icons
            $('.sort-icon').removeClass('bi-caret-up-fill bi-caret-down-fill');
            const iconClass = currentSortDir === 'asc' ? 'bi-caret-up-fill' : 'bi-caret-down-fill';
            $(`.sort-header[data-sort="${column}"] .sort-icon`).addClass(iconClass);

            // Get column index based on column name
            let colIndex;
            switch (column) {
                case 'id':
                    colIndex = 1;
                    break;
                case 'email':
                    colIndex = 2;
                    break;
                case 'username':
                    colIndex = 4;
                    break;
                case 'department':
                    colIndex = 5;
                    break;
                case 'status':
                    colIndex = 6;
                    break;
                default:
                    colIndex = 1;
            }

            // Get all rows
            const tableBody = document.getElementById('umTableBody');
            if (!tableBody) {
                console.error('Table body not found!');
                return;
            }

            const rows = Array.from(tableBody.querySelectorAll('tr'));

            // Sort the rows
            rows.sort(function(a, b) {
                const aValue = a.cells[colIndex] ? a.cells[colIndex].textContent.trim().toLowerCase() : '';
                const bValue = b.cells[colIndex] ? b.cells[colIndex].textContent.trim().toLowerCase() : '';

                // Handle numeric sorting for IDs
                if (column === 'id') {
                    return currentSortDir === 'asc' ?
                        parseInt(aValue) - parseInt(bValue) :
                        parseInt(bValue) - parseInt(aValue);
                }

                // String comparison for other columns
                if (aValue < bValue) return currentSortDir === 'asc' ? -1 : 1;
                if (aValue > bValue) return currentSortDir === 'asc' ? 1 : -1;
                return 0;
            });

            // Clear the table
            while (tableBody.firstChild) {
                tableBody.removeChild(tableBody.firstChild);
            }

            // Re-append sorted rows to the table
            rows.forEach(row => tableBody.appendChild(row));

            // Update window.allRows and window.filteredRows for pagination
            window.allRows = rows;

            // Apply any active filters
            filterTable();
        }

        // Direct client-side filtering function
        function filterTable() {
            const searchText = $('#search-filters').val().toLowerCase();
            const deptFilter = $('#department-filter').val();

            console.log('Filtering with:', {
                searchText,
                deptFilter
            });

            // Store all table rows for filtering
            const tableBody = document.getElementById('umTableBody');
            if (!tableBody) {
                console.error('Table body not found!');
                return;
            }

            const allRows = Array.from(tableBody.querySelectorAll('tr'));

            // Filter rows based on search and department filter
            window.filteredRows = allRows.filter(row => {
                const rowText = row.textContent.toLowerCase();
                let matchesSearch = true;
                let matchesDept = true;

                // Apply search filter
                if (searchText) {
                    matchesSearch = rowText.includes(searchText);
                }

                // Apply department filter
                if (deptFilter && deptFilter !== 'all') {
                    const deptCell = row.querySelector('td:nth-child(6)');
                    if (deptCell) {
                        matchesDept = deptCell.textContent.toLowerCase().includes(deptFilter.toLowerCase());
                    } else {
                        matchesDept = false;
                    }
                }

                return matchesSearch && matchesDept;
            });

            // Store the filtered rows for pagination
            window.allRows = allRows;

            // Reset to first page and update pagination
            if (window.paginationConfig) {
                window.paginationConfig.currentPage = 1;
            }

            // Call the pagination library's update function
            if (typeof updatePagination === 'function') {
                updatePagination();
            }
        }

        // Function to update the departments table in the edit user modal
        function updateEditDepartmentsDisplay() {
            // Update edit user departments display
            const $list = $('#assignedDepartmentsList');
            const $table = $('#assignedDepartmentsTable tbody');

            $list.empty();
            $table.empty();

            selectedDepartments.forEach(function(dept) {
                // Add badge to list
                $list.append(`
                    <span class="badge bg-primary me-1 mb-1">${dept.name}</span>
                `);

                // Add row to table
                $table.append(`
                    <tr data-department-id="${dept.id}">
                        <td>${dept.name}</td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-danger remove-edit-dept" data-dept-id="${dept.id}">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                `);
            });

            // Add event handlers for removal buttons
            $('.remove-edit-dept').on('click', function() {
                const deptId = $(this).data('dept-id');
                selectedDepartments = selectedDepartments.filter(d => String(d.id) !== String(deptId));
                updateEditDepartmentsDisplay();
            });
        }

        // Bind sort headers
        $('.sort-header').off('click').on('click', function(e) {
            e.preventDefault();
            const column = $(this).data('sort');
            sortTable(column);
            filterTable(); // Apply filtering after sorting
        });


        // Use the nested function structure as preferred by user
        $(function() {
            // remove any other handlers (e.g. leftover from user_management.js)
            $('#search-filters').off('input');

            // re-bind your client-side filter
            $('#search-filters').on('input', function(e) {
                filterTable(); // your existing function
            });

            // prevent ENTER from accidentally submitting anything
            $('#search-filters').on('keydown', function(e) {
                if (e.key === 'Enter') e.preventDefault();
            });

            // Bind to department select changes
            $('#department-filter').off('change').on('change', function() {
                filterTable();
            });

            // Clear filters button
            $('#clear-filters-btn').off('click').on('click', function() {
                // Clear both filters safely
                $('#search-filters').val('');
                $('#department-filter').val('all').trigger('change');

                // Reset filteredRows to all rows
                window.filteredRows = window.allRows;

                // Update pagination
                if (typeof updatePagination === 'function') {
                    // Reset to first page
                    if (window.paginationConfig) {
                        window.paginationConfig.currentPage = 1;
                    }
                    updatePagination();
                }
            });

            // Initial sort/filter (done only once)
            sortTable('id');

            // Initialize pagination if not already done
            if (typeof initPagination === 'function' && !window.paginationConfig) {
                initPagination({
                    tableId: 'umTableBody',
                    rowsPerPageSelectId: 'rowsPerPageSelect',
                    currentPageId: 'currentPage',
                    rowsPerPageId: 'rowsPerPage',
                    totalRowsId: 'totalRows',
                    prevPageId: 'prevPage',
                    nextPageId: 'nextPage',
                    paginationId: 'pagination'
                });
            }
        });

        // Handle create user form submission
        $('#submitCreateUser').on('click', function() {
            const form = $('#createUserForm');
            const formData = new FormData(form[0]);

            // Validate email has domain
            const email = $('#email').val();
            if (!validateEmail(email)) {
                $('#email').addClass('is-invalid');
                return;
            } else {
                $('#email').removeClass('is-invalid');
            }

            // Check if departments have been added
            if (selectedDepartments.length === 0) {
                // Try to get from dropdown directly as a fallback
                const selectedDept = $('#modal_department').val();
                if (selectedDept) {
                    const deptName = $('#modal_department option:selected').text();
                    selectedDepartments.push({
                        id: selectedDept,
                        name: deptName
                    });
                    updateDepartmentsDisplay();
                } else {
                    // No departments selected
                    Toast.error('At least one department must be assigned');
                    return;
                }
            }

            // Clear any existing department values to avoid duplicates
            formData.delete('departments[]');
            formData.delete('department');

            // Add all departments as array
            selectedDepartments.forEach((dept, index) => {
                formData.append(`departments[${index}]`, dept.id);
            });

            // Set a single department for compatibility with older backend code
            if (selectedDepartments.length > 0) {
                formData.append('department', selectedDepartments[0].id);
            }

            $.ajax({
                url: form.attr('action'),
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    const result = (typeof response === 'string') ? JSON.parse(response) : response;
                    if (result.success) {
                        Toast.success('User created successfully');

                        // only now clear everything:
                        form[0].reset();
                        selectedDepartments = [];
                        $('#modal_department').val(null).trigger('change');
                        $('#createAssignedDepartmentsTable tbody').empty();
                        // reset your password-strength UI & toggle-icon here too…

                        $('#createUserModal').modal('hide');
                    } else {
                        Toast.error(result.message || 'Failed to create user');
                    }
                },
                error: function(xhr, status, error) {
                    try {
                        // First try to extract JSON from the response if it contains HTML errors
                        let jsonStr = xhr.responseText;
                        if (jsonStr.includes('{') && jsonStr.includes('}')) {
                            jsonStr = jsonStr.substring(jsonStr.indexOf('{'), jsonStr.lastIndexOf('}') + 1);
                            const result = JSON.parse(jsonStr);
                            alert('Error: ' + (result.message || 'Failed to create user'));
                        } else {
                            const result = JSON.parse(xhr.responseText);
                            alert('Error: ' + (result.message || 'Failed to create user'));
                        }
                    } catch (e) {
                        // If there's a username error in the response text, extract and show it
                        if (xhr.responseText.includes('username is already taken')) {
                            alert('Error: Username is already taken. Please try a different username.');
                        } else {
                            alert('Server error occurred. Please try again.');
                        }
                        console.error('Parse error:', e);
                    }
                }
            });
        });

        // Email input validation on change/input
        $('#email').on('input', function() {
            const email = $(this).val();
            if (validateEmail(email)) {
                $(this).removeClass('is-invalid');
            }
        });

        $('#editEmail').on('input', function() {
            const email = $(this).val();
            if (validateEmail(email)) {
                $(this).removeClass('is-invalid');
            }
        });

        // Reset selectedDepartments when the modal is closed
        $('#createUserModal').on('hidden.bs.modal', function() {
            const $modal = $(this);

            // 1) Reset the entire form (clears all <input>, <select>, etc.)
            $modal.find('form')[0].reset();

            // 2) If you're using Select2 on #modal_department, clear it
            const $dept = $modal.find('#modal_department');
            if ($dept.hasClass('select2-hidden-accessible')) {
                $dept.val(null).trigger('change');
            }

            // 3) Hide & clear your "custom department" text field
            const $custom = $modal.find('#modal_custom_department');
            $custom.val('').hide();

            // 4) Empty the Assigned Departments table
            $modal.find('#createAssignedDepartmentsTable tbody').empty();

            // 5) Reset selectedDepartments array
            selectedDepartments = [];

            // 6) (Optional) Reset any password-strength UI or other bits
            $modal.find('.password-strength').addClass('d-none');
            $modal.find('.progress-bar')
                .css('width', '0%')
                .attr('aria-valuenow', '0');
            const $pwdToggleIcon = $modal.find('.toggle-password i');
            $modal.find('#password').attr('type', 'password');
            $pwdToggleIcon.removeClass('bi-eye-slash').addClass('bi-eye');
        });
    </script>
</body>

</html>