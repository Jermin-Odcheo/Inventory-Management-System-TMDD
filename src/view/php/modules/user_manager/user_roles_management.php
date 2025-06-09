<?php
/**
 * User Roles Management Module
 *
 * This file provides comprehensive functionality for managing user role assignments in the system. It handles the creation, modification, and deletion of role assignments, ensuring proper access control and permission management. The module ensures proper validation, user authorization, and maintains data consistency across the system.
 *
 * @package    InventoryManagementSystem
 * @subpackage UserManager
 * @author     TMDD Interns 25'
 */

session_start();


// Include configuration (assumes config.php defines a PDO instance in $pdo)
require_once('../../../../../config/ims-tmdd.php');
include '../../general/header.php';
include '../../general/sidebar.php';
include '../../general/footer.php';

echo '<script>document.body.classList.add("user-roles-management");</script>';

/**
 * Validate that the current session has a logged-in user.
 *
 * @var int|null $userId The current user ID from session, or null if not set.
 */
$userId = $_SESSION['user_id'] ?? null;
if (!is_int($userId) && !ctype_digit((string)$userId)) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}
$userId = (int)$userId;

/**
 * Initialize RBAC and enforce "View" privilege.
 *
 * @var RBACService $rbac The RBAC service instance.
 */
$rbac = new RBACService($pdo, $_SESSION['user_id']);
$rbac->requirePrivilege('User Management', 'View');

// 3) Button flags
/**
 * @var mixed $canCreate True if the user has the "Create" privilege, false otherwise.
 *
 */
$canCreate = $rbac->hasPrivilege('User Management', 'Create');
/**
 * @var mixed $canModify True if the user has the "Modify" privilege, false otherwise.
 */
$canModify = $rbac->hasPrivilege('User Management', 'Modify');
/**
 * @var mixed $canRemove True if the user has the "Remove" privilege, false otherwise.
 */
$canRemove = $rbac->hasPrivilege('User Management', 'Remove');
/**
 * @var mixed $canTrack True if the user has the "Track" privilege, false otherwise.
 */
$canTrack  = $rbac->hasPrivilege('User Management', 'Track');

// --- START SORTING IMPLEMENTATION ---

// Define allowed sortable columns and their corresponding database columns/aliases
/**
 * Allowed sortable columns mapped to their database expressions.
 * @var array $sortMap Associative array mapping sortable column names to their corresponding database columns/aliases.
 */
$sortMap = [
    'username'    => 'u.username',
    'departments' => 'departments_concat', // Alias from GROUP_CONCAT
    'roles'       => 'roles_concat',       // Alias from GROUP_CONCAT
];

// Get sort parameters from GET request
/**
 * Sort by parameter from GET request.
 * @var string $sortBy Default sort by username.
 */
$sortBy = $_GET['sort_by'] ?? 'username'; // Default sort by username
/**
 * Sort direction parameter from GET request.
 * @var string $sortDir Default sort order 'asc'.
 */
$sortDir = strtolower($_GET['sort_order'] ?? 'asc'); // Default sort order 'asc'

/**
 * Validate sort by parameter against the allowed map.
 * @var string $sortBy Default sort by username.
 */
if (!isset($sortMap[$sortBy])) {
    $sortBy = 'username'; // Fallback to default if invalid
}

/**
 * Validate sort direction.
 * @var string $sortDir Default sort order 'asc'.
 */
if (!in_array($sortDir, ['asc', 'desc'])) {
    $sortDir = 'asc'; // Fallback to default if invalid
}

/**
 * Construct the ORDER BY clause dynamically.
 * @var string $orderByClause ORDER BY clause.
 */
$orderByClause = "ORDER BY " . $sortMap[$sortBy] . " " . $sortDir;

// --- END SORTING IMPLEMENTATION ---

// --- START FILTERING IMPLEMENTATION ---

// Get filter parameters from GET request
/**
 * Raw search filter from GET parameters, trimmed of whitespace.
 *
 * @var string $searchFilter
 */
$searchFilter = isset($_GET['search']) ? trim($_GET['search']) : '';
/**
 * Role filter from GET parameters, cast to integer. Zero means no filter.
 *
 * @var int $roleFilter
 */
$roleFilter = isset($_GET['role']) ? (int)$_GET['role'] : 0;

// Build WHERE clause for filtering
/**
 * WHERE clause for filtering.
 *
 * @var string $whereClause
 */
$whereClause = "u.is_disabled = 0";

/**
 * Add search filter if provided.
 */
if (!empty($searchFilter)) {
    // Remove any existing % characters from the search term
    $searchFilter = str_replace('%', '', $searchFilter);
    // Properly escape the search term for SQL
    $searchFilter = $pdo->quote($searchFilter);
    $searchFilter = trim($searchFilter, "'"); // Remove quotes added by PDO::quote
    $whereClause .= " AND (u.username LIKE '%{$searchFilter}%' OR 
                          u.email LIKE '%{$searchFilter}%' OR 
                          u.first_name LIKE '%{$searchFilter}%' OR 
                          u.last_name LIKE '%{$searchFilter}%' OR 
                          d.department_name LIKE '%{$searchFilter}%' OR 
                          r.role_name LIKE '%{$searchFilter}%')";
}
/**
 * Add role filter if provided.
 */
if ($roleFilter > 0) {
    $whereClause .= " AND r.id = {$roleFilter}";
}

// --- END FILTERING IMPLEMENTATION ---

// --- START SERVER-SIDE PAGINATION ---

/**
 * Current page from URL parameter.
 *
 * @var int $currentPage
 */
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;


/**
 * Default rows per page.
 *
 * @var int $rowsPerPage
 */
$rowsPerPage = isset($_GET['rows_per_page']) ? max(10, intval($_GET['rows_per_page'])) : 10;

/**
 * Offset for SQL LIMIT clause, calculated from current page and rows per page.
 *
 * @var int $offset
 */
$offset = ($currentPage - 1) * $rowsPerPage;

// --- END SERVER-SIDE PAGINATION ---

// Query active users with their roles and departments for display and sorting
// Use GROUP_CONCAT to get all departments and roles for a user in a single row
/**
 * Fetch total number of distinct active users matching filters.
 *
 * @var PDOStatement $countStmt
 */
$countStmt = $pdo->query(
    "SELECT COUNT(DISTINCT u.id) AS total_users
    FROM
        users u
    LEFT JOIN
        user_department_roles udr ON u.id = udr.user_id
    LEFT JOIN
        departments d ON udr.department_id = d.id
    LEFT JOIN
        roles r ON udr.role_id = r.id
    WHERE
        {$whereClause}"
);
/**
 * Total number of distinct active users matching filters.
 *
 * @var int $totalUsersCount
 */
$totalUsersCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total_users'];

/**
 * Total number of pages for pagination.
 *
 * @var int $totalPages
 */
$totalPages = ceil($totalUsersCount / $rowsPerPage);

/**
 * Query with pagination, filtering, and sorting.
 *
 * @var PDOStatement $stmt
 */
$stmt = $pdo->prepare(
    "SELECT
        u.id,
        u.username,
        u.email,
        u.first_name,
        u.last_name,
        u.date_created,
        u.status,
        GROUP_CONCAT(DISTINCT d.department_name ORDER BY d.department_name SEPARATOR ', ') AS departments_concat,
        GROUP_CONCAT(DISTINCT r.role_name ORDER BY r.role_name SEPARATOR ', ') AS roles_concat
    FROM
        users u
    LEFT JOIN
        user_department_roles udr ON u.id = udr.user_id
    LEFT JOIN
        departments d ON udr.department_id = d.id
    LEFT JOIN
        roles r ON udr.role_id = r.id
    WHERE
        {$whereClause}
    GROUP BY
        u.id, u.username, u.email, u.first_name, u.last_name, u.date_created, u.status
    {$orderByClause}
    LIMIT :limit OFFSET :offset"
);

/**
 * Bind parameters to the query.
 * Bind offset to the query.
 * Execute the query.
 * Fetch all users data.
 */
$stmt->bindParam(':limit', $rowsPerPage, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
/**
 * Fetch all users data.
 *
 * @var array $usersData
 */
$usersData = $stmt->fetchAll(PDO::FETCH_ASSOC);


/**
 * Actual count of users (after filtering).
 * Store the actual count of users (after filtering)
 * @var int $totalUsers
 */
$totalUsers = $totalUsersCount;

/**
 * Query active roles (for dropdowns).
 *
 * @var PDOStatement $stmt
 */
$stmt = $pdo->query("SELECT id, role_name FROM roles WHERE is_disabled = 0");
$rolesData = $stmt->fetchAll();

/**
 * Query all departments (for dropdowns).
 *
 * @var PDOStatement $stmt
 */
$stmt = $pdo->query("SELECT id, department_name, abbreviation FROM departments WHERE is_disabled = 0 ORDER BY department_name");
$departmentsData = $stmt->fetchAll();

/**
 * Fetch all user–department–role triples (for detailed client-side mapping in modals).
 * This is still needed as the main query above only provides concatenated strings
 * and the client-side logic needs individual department and role IDs for editing.
 *
 * @var PDOStatement $stmt
 */
$stmt = $pdo->query(
    "SELECT user_id, role_id, department_id
     FROM user_department_roles
     WHERE 1"
);
$triples = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * Build a map keyed by "userId–roleId" to collect department IDs.
 *
 * @var array $userRoleMap
 */
$userRoleMap = [];
foreach ($triples as $t) {
    $userId = (int)$t['user_id'];
    $roleId = $t['role_id'] !== null ? (int)$t['role_id'] : null;
    $deptId = (int)$t['department_id'];

    // Use a special key format for null roles to group assignments correctly
    $key = $roleId !== null ? "{$userId}-{$roleId}" : "{$userId}-null";

    if (!isset($userRoleMap[$key])) {
        $userRoleMap[$key] = [
            'userId'        => $userId,
            'roleId'        => $roleId,
            'departmentIds' => [],
        ];
    }

    // Add department ID to the list for this user-role combination
    if (!in_array($deptId, $userRoleMap[$key]['departmentIds'], true)) {
        $userRoleMap[$key]['departmentIds'][] = $deptId;
    }
}

// Re-index for numeric array for JavaScript consumption
$userRoleDepartments = array_values($userRoleMap);

/**
 * User Roles Management Class
 * 
 * Handles all user role management operations including CRUD operations,
 * role assignments, and user-role relationships.
 *
 * @package    InventoryManagementSystem
 * @subpackage UserManagement
 * @author     TMDD Interns 25'
 * @version    1.0.0
 */
class UserRolesManagement {
    /**
     * Current user ID from session
     *
     * @var int|null
     */
    private $userId;

    /**
     * RBAC service instance
     *
     * @var RBACService
     */
    private $rbac;

    /**
     * User permissions
     *
     * @var array
     */
    private $permissions = [
        'canCreate' => false,
        'canModify' => false,
        'canRemove' => false,
        'canTrack' => false
    ];

    /**
     * Sort configuration
     *
     * @var array
     */
    private $sortConfig = [
        'columns' => [
            'username' => 'u.username',
            'departments' => 'd.department_name',
            'roles' => 'r.role_name'
        ],
        'default' => 'username',
        'direction' => 'asc'
    ];

    /**
     * Constructor
     *
     * Initializes the user roles management system
     *
     * @param int|null $userId The current user's ID
     * @param RBACService $rbac The RBAC service instance
     */
    public function __construct($userId, RBACService $rbac) {
        $this->userId = $userId;
        $this->rbac = $rbac;
        $this->initializePermissions();
    }

    /**
     * Initialize user permissions
     *
     * Sets up the user's permissions based on their role
     *
     * @return void
     */
    private function initializePermissions() {
        // ... existing code ...
    }

    /**
     * Get users with roles
     *
     * Retrieves a list of users with their associated roles
     *
     * @param array $filters Search and filter parameters
     * @param int $page Current page number
     * @param int $perPage Number of items per page
     * @return array List of users with their roles
     */
    public function getUsersWithRoles($filters = [], $page = 1, $perPage = 10) {
        // ... existing code ...
    }

    /**
     * Add user to role
     *
     * Assigns a user to a specific role
     *
     * @param int $userId User ID
     * @param int $roleId Role ID
     * @return bool Success status
     */
    public function addUserToRole($userId, $roleId) {
        // ... existing code ...
    }

    /**
     * Remove user from role
     *
     * Removes a user's role assignment
     *
     * @param int $userId User ID
     * @param int $roleId Role ID
     * @return bool Success status
     */
    public function removeUserFromRole($userId, $roleId) {
        // ... existing code ...
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>src/view/styles/css/user_module.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <title>User Roles Management</title>
    <style>
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

        /* Enhanced pagination styles */
        .pagination {
            display: inline-flex;
            flex-wrap: wrap;
            gap: 4px;
            margin-bottom: 0;
        }

        .pagination .page-item .page-link {
            min-width: 36px;
            height: 36px;
            text-align: center;
            font-weight: 500;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.375rem;
            border-radius: 0.25rem;
        }

        .pagination .page-item .page-link i {
            font-size: 0.875rem;
        }

        /* Sortable column header styles */
        .sortable,
        .sort-header {
            cursor: pointer;
            position: relative;
            padding-right: 20px !important;
            transition: all 0.2s ease;
            display: block;
            width: 100%;
            color: #212529;
            text-decoration: none;
        }

        .sortable:hover,
        .sort-header:hover {
            background-color: #f8f9fa;
            color: #0d6efd;
        }

        .sortable i,
        .sort-header i {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            transition: all 0.2s ease;
        }

        .sortable:hover i,
        .sort-header:hover i {
            color: #0d6efd;
        }

        /* Style for active sort column */
        .sort-header.active-sort {
            background-color: #e9f0ff;
            font-weight: 600;
        }

        .sort-header.active-sort i {
            color: #0d6efd;
            font-weight: bold;
        }

        /* Loading state for sort headers */
        .sort-header.sorting {
            opacity: 0.7;
        }

        /* Improved sort icon styling */
        .sort-icon {
            font-size: 0.75rem;
            margin-left: 5px;
            vertical-align: middle;
        }

        /* Center pagination on mobile */
        @media (max-width: 767.98px) {
            .pagination {
                justify-content: center;
                margin: 0.5rem 0;
            }

            /* Center info text on mobile */
            .text-muted {
                text-align: center;
                margin-bottom: 0.5rem;
            }

            /* Center prev/next buttons on mobile */
            .justify-content-md-end {
                justify-content: center !important;
                margin-top: 0.5rem;
            }
        }

        .container-fluid {
            padding: 20px
        }

        /* Updated filters container styling */
        .filters-container {
            background-color: #f8f9fa;
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }

        .filters-container h5 {
            color: #495057;
            font-weight: 600;
        }

        .form-label {
            font-weight: 500;
            color: #495057;
            margin-bottom: 0.5rem;
        }

        .form-select,
        .form-control {
            border-radius: 0.375rem;
            border: 1px solid #ced4da;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
        }

        .form-select:focus,
        .form-control:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }

        .input-group-text {
            background-color: #f8f9fa;
            border: 1px solid #ced4da;
            border-right: none;
        }

        .input-group .form-control {
            border-left: none;
        }

        .input-group .form-control:focus {
            border-left: none;
        }

        .btn {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .btn i {
            margin-right: 0.5rem;
        }

        .action-buttons {
            border-top: 1px solid #dee2e6;
            padding-top: 1rem;
        }
    </style>
</head>

<body>
    <div class="main-content container-fluid">
        <header>
            <h1>USER ROLES MANAGER</h1>
        </header>

        <div class="filters-container">
            <form id="filter-form" method="get" class="w-100">
                <div class="d-flex gap-3 align-items-end">

                    <?php if ($canCreate): ?>
                        <div class="form-group">
                            <label class="form-label">&nbsp;</label>
                            <button type="button" id="create-btn" class="btn btn-dark">
                                <i class="bi bi-plus-lg"></i> Add Role/s to User/s
                            </button>
                        </div>
                    <?php endif; ?>
                    <div class="form-group flex-grow-1">
                        <label for="role-filter" class="form-label">Filter by Role</label>
                        <select id="role-filter" name="role" class="form-select">
                            <option value="">All Roles</option>
                            <?php foreach ($rolesData as $role): ?>
                                <option value="<?php echo $role['id']; ?>" <?= $roleFilter == $role['id'] ? 'selected' : '' ?>>
                                    <?php echo htmlspecialchars($role['role_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group flex-grow-1">
                        <label for="search-users" class="form-label">Search</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" id="search-users" name="search" class="form-control"
                                placeholder="Search users, departments, roles..."
                                value="<?= htmlspecialchars($searchFilter) ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="submit" id="filter-btn" class="btn btn-dark">
                                <i class="bi bi-funnel"></i> Filter
                            </button>
                            <button type="button" id="clear-btn" class="btn btn-secondary">
                                <i class="bi bi-x-circle"></i> Clear
                            </button>
                        </div>
                    </div>

                    <?php if ($rbac->hasPrivilege('User Management', 'Modify')): ?>
                        <div class="form-group">
                            <label class="form-label">&nbsp;</label>
                            <a href="user_management.php" class="btn btn-primary">
                                <i class="bi bi-person-gear"></i> Manage User Accounts
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <?php
                // Preserve sort parameters if they exist
                if (isset($_GET['sort_by']) && isset($_GET['sort_order'])):
                ?>
                    <input type="hidden" name="sort_by" value="<?= htmlspecialchars($_GET['sort_by']) ?>">
                    <input type="hidden" name="sort_order" value="<?= htmlspecialchars($_GET['sort_order']) ?>">
                <?php endif; ?>
            </form>
        </div>

        <div class="table-responsive" id="table">
            <table class="table table-striped table-hover" id="urTable">
                <thead>
                    <tr>
                        <th>
                            <a href="#" class="sort-header <?php echo $sortBy === 'username' ? 'active-sort' : ''; ?>" data-sort="username">
                                User
                                <i class="bi <?php echo $sortBy === 'username' ? ($sortDir === 'asc' ? 'bi-caret-up-fill' : 'bi-caret-down-fill') : 'bi-caret-up-fill'; ?> sort-icon"></i>
                            </a>
                        </th>
                        <th>
                            <a href="#" class="sort-header <?php echo $sortBy === 'departments' ? 'active-sort' : ''; ?>" data-sort="departments">
                                Departments
                                <i class="bi <?php echo $sortBy === 'departments' ? ($sortDir === 'asc' ? 'bi-caret-up-fill' : 'bi-caret-down-fill') : 'bi-caret-up-fill'; ?> sort-icon"></i>
                            </a>
                        </th>
                        <th>
                            <a href="#" class="sort-header <?php echo $sortBy === 'roles' ? 'active-sort' : ''; ?>" data-sort="roles">
                                Roles
                                <i class="bi <?php echo $sortBy === 'roles' ? ($sortDir === 'asc' ? 'bi-caret-up-fill' : 'bi-caret-down-fill') : 'bi-caret-up-fill'; ?> sort-icon"></i>
                            </a>
                        </th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($usersData)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">No users found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($usersData as $user): ?>
                            <tr>
                                <td><?= htmlspecialchars($user['username']); ?></td>
                                <td><?= htmlspecialchars($user['departments_concat'] ?? 'Not assigned'); ?></td>
                                <td><?= htmlspecialchars($user['roles_concat'] ?? 'No roles assigned'); ?></td>
                                <td>
                                    <?php if ($canModify): ?>
                                        <button class="btn-outline-primary edit-btn"
                                            data-user-id="<?= htmlspecialchars($user['id']); ?>"
                                            data-username="<?= htmlspecialchars($user['username']); ?>">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($canRemove): ?>
                                        <button class="btn-outline-danger delete-btn"
                                            data-user-id="<?= htmlspecialchars($user['id']); ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="container-fluid">
                <div class="row align-items-center g-3">
                    <div class="col-12 col-md-4">
                        <div class="text-muted">
                            <?php
                            // Use the actual user count from server-side pagination
                            $displayStart = ($currentPage - 1) * $rowsPerPage + 1;
                            $displayEnd = min($currentPage * $rowsPerPage, $totalUsers);
                            ?>
                            <input type="hidden" id="total-users" value="<?= $totalUsers ?>">
                            <input type="hidden" id="current-page" value="<?= $currentPage ?>">
                            <input type="hidden" id="rows-per-page" value="<?= $rowsPerPage ?>">
                            <input type="hidden" id="total-pages" value="<?= $totalPages ?>">
                            Showing <span id="currentPage"><?= $displayStart ?></span> to <span id="rowsPerPage"><?= $displayEnd ?></span> of <span id="totalRows"><?= $totalUsers ?></span> entries
                        </div>
                    </div>
                    <div class="col-12 col-md-4 text-center">
                        <nav aria-label="Page navigation">
                            <ul class="pagination pagination-sm d-inline-flex justify-content-center mb-0" id="pagination">
                                <?php
                                // Previous button
                                $prevDisabled = ($currentPage <= 1) ? ' disabled' : '';
                                $prevPage = max(1, $currentPage - 1);
                                $prevUrl = '?page=' . $prevPage;
                                // Add sort parameters if they exist
                                if (isset($_GET['sort_by']) && isset($_GET['sort_order'])) {
                                    $prevUrl .= '&sort_by=' . urlencode($_GET['sort_by']) . '&sort_order=' . urlencode($_GET['sort_order']);
                                }
                                ?>
                                <li class="page-item<?= $prevDisabled ?>">
                                    <a class="page-link" href="<?= $prevUrl ?>" aria-label="Previous">
                                        <i class="bi bi-chevron-left"></i>
                                    </a>
                                </li>

                                <?php
                                // First page
                                if ($currentPage > 3) {
                                    $firstUrl = '?page=1';
                                    if (isset($_GET['sort_by']) && isset($_GET['sort_order'])) {
                                        $firstUrl .= '&sort_by=' . urlencode($_GET['sort_by']) . '&sort_order=' . urlencode($_GET['sort_order']);
                                    }
                                ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?= $firstUrl ?>">1</a>
                                    </li>
                                    <?php
                                    if ($currentPage > 4) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                }

                                // Page numbers
                                $startPage = max(1, $currentPage - 1);
                                $endPage = min($totalPages, $currentPage + 1);

                                for ($i = $startPage; $i <= $endPage; $i++) {
                                    $isActive = ($i == $currentPage) ? ' active' : '';
                                    $pageUrl = '?page=' . $i;
                                    if (isset($_GET['sort_by']) && isset($_GET['sort_order'])) {
                                        $pageUrl .= '&sort_by=' . urlencode($_GET['sort_by']) . '&sort_order=' . urlencode($_GET['sort_order']);
                                    }
                                    ?>
                                    <li class="page-item<?= $isActive ?>">
                                        <a class="page-link" href="<?= $pageUrl ?>"><?= $i ?></a>
                                    </li>
                                <?php
                                }

                                // Last page
                                if ($currentPage < $totalPages - 2) {
                                    if ($currentPage < $totalPages - 3) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                    $lastUrl = '?page=' . $totalPages;
                                    if (isset($_GET['sort_by']) && isset($_GET['sort_order'])) {
                                        $lastUrl .= '&sort_by=' . urlencode($_GET['sort_by']) . '&sort_order=' . urlencode($_GET['sort_order']);
                                    }
                                ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?= $lastUrl ?>"><?= $totalPages ?></a>
                                    </li>
                                <?php
                                }

                                // Next button
                                $nextDisabled = ($currentPage >= $totalPages) ? ' disabled' : '';
                                $nextPage = min($totalPages, $currentPage + 1);
                                $nextUrl = '?page=' . $nextPage;
                                if (isset($_GET['sort_by']) && isset($_GET['sort_order'])) {
                                    $nextUrl .= '&sort_by=' . urlencode($_GET['sort_by']) . '&sort_order=' . urlencode($_GET['sort_order']);
                                }
                                ?>
                                <li class="page-item<?= $nextDisabled ?>">
                                    <a class="page-link" href="<?= $nextUrl ?>" aria-label="Next">
                                        <i class="bi bi-chevron-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="d-flex align-items-center gap-2 justify-content-md-end">
                            <button id="prevPage" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1" <?= ($currentPage <= 1) ? 'disabled' : '' ?>>
                                <i class="bi bi-chevron-left"></i> Previous
                            </button>
                            <form method="get" class="d-inline-flex">
                                <?php if (isset($_GET['sort_by']) && isset($_GET['sort_order'])): ?>
                                    <input type="hidden" name="sort_by" value="<?= htmlspecialchars($_GET['sort_by']) ?>">
                                    <input type="hidden" name="sort_order" value="<?= htmlspecialchars($_GET['sort_order']) ?>">
                                <?php endif; ?>
                                <select id="rowsPerPageSelect" name="rows_per_page" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                                    <option value="10" <?= $rowsPerPage == 10 ? 'selected' : '' ?>>10</option>
                                    <option value="20" <?= $rowsPerPage == 20 ? 'selected' : '' ?>>20</option>
                                    <option value="30" <?= $rowsPerPage == 30 ? 'selected' : '' ?>>30</option>
                                    <option value="50" <?= $rowsPerPage == 50 ? 'selected' : '' ?>>50</option>
                                </select>
                            </form>
                            <button id="nextPage" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1" <?= ($currentPage >= $totalPages) ? 'disabled' : '' ?>>
                                Next <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <div id="add-user-roles-modal" class="modal">
        <div class="modal-content">
            <h2>add user to roles modal</h2>
            <div class="modal-body">
                <div class="form-group">
                    <label for="search-department-dropdown">select department <span class="text-danger">*</span></label>
                    <select id="search-department-dropdown">
                        <option value="">Select one department</option>
                        <?php foreach ($departmentsData as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>"><?php echo '(' . htmlspecialchars($dept['abbreviation']) . ') ' . htmlspecialchars($dept['department_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Department is required</small>
                </div>
                <div class="form-group">
                    <label>selected department</label>
                    <div id="selected-department-container"></div>
                </div>
                <div class="form-group">
                    <label for="search-role-dropdown">search role/s (optional)</label>
                    <select id="search-role-dropdown">
                        <option value="">Select roles</option>
                        <?php foreach ($rolesData as $role): ?>
                            <option value="<?php echo $role['id']; ?>"><?php echo htmlspecialchars($role['role_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Leave empty for assignments without roles</small>
                </div>
                <div class="form-group">
                    <label>current role selection</label>
                    <div id="selected-roles-container"></div>
                </div>
                <div class="form-group">
                    <label for="search-users-dropdown">search user/s</label>
                    <select id="search-users-dropdown">
                        <option value="">Select users</option>
                        <?php foreach ($usersData as $user): ?>
                            <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['username']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>current user selection</label>
                    <div id="selected-users-container"></div>
                </div>
                <div class="form-group">
                    <label>list of current users</label>
                    <table id="current-users-table">
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button id="close-user-roles-modal">Cancel</button>
                <button id="save-user-roles">Save</button>
            </div>
        </div>
    </div>

    <div id="add-department-role-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Add Role to Department</h2>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>User</label>
                    <div id="edit-user-info" class="info-field"></div>
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <div id="edit-department-info" class="info-field"></div>
                </div>
                <div class="form-group">
                    <label for="department-dropdown">Add Role to Department (optional)</label>
                    <select id="department-dropdown" class="form-control">
                        <option value="">Select role</option>
                        <?php foreach ($rolesData as $role): ?>
                            <option value="<?php echo $role['id']; ?>"><?php echo htmlspecialchars($role['role_name']); ?></option>
                        <?php endforeach; ?>

                    </select>
                    <small class="form-text text-muted">You can save without adding any roles</small>
                </div>
                <div class="form-group">
                    <label>Assigned Roles Table</label>
                    <div class="department-table-container">
                        <table class="table table-striped table-hover" id="assigned-roles-table">
                            <thead>
                                <tr>
                                    <th>Role Name</th>
                                    <th class="text-end" style="width: 60px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button id="close-department-role-modal" class="btn btn-secondary">Cancel</button>
                <button id="save-department-role" class="btn btn-primary">Save</button>
            </div>
        </div>
    </div>

    <div class="modal fade" id="delete-confirm-modal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lower">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmDeleteModalLabel">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this role assignment?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="cancel-delete-btn" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirm-delete-btn">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let usersData = <?php echo json_encode($usersData); ?>;
        let rolesData = <?php echo json_encode($rolesData); ?>;
        let departmentsData = <?php echo json_encode($departmentsData); ?>;
        let userRoleDepartments = <?php echo json_encode($userRoleDepartments); ?>;

        // Pass RBAC privileges to JavaScript
        const userPrivileges = {
            canCreate: <?php echo json_encode($canCreate); ?>,
            canModify: <?php echo json_encode($canModify); ?>,
            canDelete: <?php echo json_encode($canRemove); ?>,
            canTrack: <?php echo json_encode($canTrack); ?>
        };
        // Pass current sort state to JavaScript
        var currentSortBy = "<?php echo htmlspecialchars($sortBy); ?>";
        var currentSortOrder = "<?php echo htmlspecialchars($sortDir); ?>";
    </script>

    <script type="text/javascript" src="<?php echo BASE_URL; ?>src/control/js/user_roles_management.js" defer></script>
    <script type="text/javascript" src="<?php echo BASE_URL; ?>src/control/js/user_roles_fixes.js" defer></script>
    <script type="text/javascript" src="<?php echo BASE_URL; ?>src/control/js/pagination.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize the Previous and Next buttons to use the server-side pagination
            $('#prevPage').on('click', function(e) {
                e.preventDefault();
                if (!$(this).prop('disabled')) {
                    const currentPage = parseInt($('#current-page').val());
                    if (currentPage > 1) {
                        navigateToPage(currentPage - 1);
                    }
                }
            });

            $('#nextPage').on('click', function(e) {
                e.preventDefault();
                if (!$(this).prop('disabled')) {
                    const currentPage = parseInt($('#current-page').val());
                    const totalPages = parseInt($('#total-pages').val());
                    if (currentPage < totalPages) {
                        navigateToPage(currentPage + 1);
                    }
                }
            });

            // Function to navigate to a specific page while preserving sort parameters
            function navigateToPage(page) {
                const urlParams = new URLSearchParams(window.location.search);
                urlParams.set('page', page);

                // Preserve sort parameters
                const sortBy = urlParams.get('sort_by');
                const sortOrder = urlParams.get('sort_order');
                if (sortBy && sortOrder) {
                    urlParams.set('sort_by', sortBy);
                    urlParams.set('sort_order', sortOrder);
                }

                // Preserve rows per page if set
                const rowsPerPage = $('#rowsPerPageSelect').val();
                if (rowsPerPage) {
                    urlParams.set('rows_per_page', rowsPerPage);
                }

                // Preserve filter parameters
                const searchValue = urlParams.get('search');
                if (searchValue) {
                    urlParams.set('search', searchValue);
                }

                const roleValue = urlParams.get('role');
                if (roleValue) {
                    urlParams.set('role', roleValue);
                }

                // Navigate to the new URL
                window.location.href = window.location.pathname + '?' + urlParams.toString();
            }

            // Handle clear button click
            $('#clear-btn').on('click', function() {
                // Get current sort parameters to preserve them
                const urlParams = new URLSearchParams(window.location.search);
                const sortBy = urlParams.get('sort_by');
                const sortOrder = urlParams.get('sort_order');

                // Create a new URL with only sort parameters if they exist
                let newUrl = window.location.pathname;
                if (sortBy && sortOrder) {
                    newUrl += `?sort_by=${sortBy}&sort_order=${sortOrder}`;
                }

                // Navigate to the new URL
                window.location.href = newUrl;
            });

            // Update the active sort header based on URL parameters
            function updateActiveSortHeader() {
                const urlParams = new URLSearchParams(window.location.search);
                const sortBy = urlParams.get('sort_by') || 'username';
                const sortOrder = urlParams.get('sort_order') || 'asc';

                // Remove active class and reset icons
                $('.sort-header').removeClass('active-sort');
                $('.sort-icon').removeClass('bi-caret-up-fill bi-caret-down-fill').addClass('bi-caret-up-fill');

                // Find the active header and update it
                const activeHeader = $(`.sort-header[data-sort="${sortBy}"]`);
                if (activeHeader.length) {
                    activeHeader.addClass('active-sort');
                    const icon = activeHeader.find('.sort-icon');
                    if (sortOrder === 'asc') {
                        icon.removeClass('bi-caret-down-fill').addClass('bi-caret-up-fill');
                    } else {
                        icon.removeClass('bi-caret-up-fill').addClass('bi-caret-down-fill');
                    }
                }
            }

            // Call the function on page load
            updateActiveSortHeader();

            // Disable client-side pagination since we're using server-side pagination
            if (typeof window.updatePagination === 'function') {
                // Store the original function
                const originalUpdatePagination = window.updatePagination;

                // Override with a version that doesn't modify the DOM
                window.updatePagination = function() {
                    console.log('Client-side pagination disabled - using server-side pagination');
                    return;
                };
            }

            // Initialize Select2 for filter dropdowns
            $('#role-filter').select2({
                placeholder: 'Select...',
                allowClear: true,
                width: '100%'
            });
        });
    </script>
</body>

</html>