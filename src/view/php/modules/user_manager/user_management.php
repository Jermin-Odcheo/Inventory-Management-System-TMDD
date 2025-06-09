<?php
/**
 * User Management Module
 *
 * This file provides comprehensive functionality for managing users in the system. It serves as the central hub for user management operations, including creation, modification, deletion, and assignment of user roles and departments. The module ensures proper validation, user authorization, and maintains data consistency across the system.
 *
 * @package    InventoryManagementSystem
 * @subpackage UserManager
 * @author     TMDD Interns 25'
 */
declare(strict_types=1);

require_once '../../../../../config/ims-tmdd.php';
session_start();

/**
 * Enable full error reporting for debugging purposes.
 */
ini_set('display_errors', '1');
error_reporting(E_ALL);


    include '../../general/header.php';
    include '../../general/sidebar.php';
    include '../../general/footer.php';

// Add user-management class to body
echo '<script>document.body.classList.add("user-management");</script>';

/**
 * Ensure a valid user_id is present in the session.
 *
 * If no valid integer user_id is found, redirect to the login page.
 *
 * @var int|null $userId The current user ID from session, or null if not set.
 */
$userId = $_SESSION['user_id'] ?? null;
if (!is_int($userId) && !ctype_digit((string)$userId)) {
    header("Location: " . BASE_URL . "index.php");
    exit();
}
$userId = (int)$userId;

/**
 * Initialize RBACService and require the "View" privilege for User Management.
 *
 * @var RBACService $rbac Instance to check user privileges.
 */
$rbac = new RBACService($pdo, $_SESSION['user_id']);
$rbac->requirePrivilege('User Management', 'View');

/**
 * Button flags based on the current user's privileges:
 *  - $canCreate:   Can create new users.
 *  - $canModify:   Can modify existing users.
 *  - $canDelete:   Can delete users.
 *  - $canTrack:    Can track user activity.
 *
 * @var bool $canCreate
 * @var bool $canModify
 * @var bool $canDelete
 * @var bool $canTrack
 */
$canCreate = $rbac->hasPrivilege('User Management', 'Create');
$canModify = $rbac->hasPrivilege('User Management', 'Modify');
$canDelete = $rbac->hasPrivilege('User Management', 'Remove');
$canTrack = $rbac->hasPrivilege('User Management', 'Track');

/**
 * Array to hold active departments keyed by their ID.
 *
 * @var array<int,array{ id:string, department_name:string, abbreviation:string }> $departments
 */
$departments = [];
try {
    /**
     * Query to retrieve all active departments, ordered alphabetically.
     */
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

/**
 * Retrieve all departments assigned to a given user.
 *
 * @param PDO $pdo    PDO instance connected to the database.
 * @param int $userId The ID of the user whose departments will be fetched.
 *
 * @return array<int,array{ department_id:string, department_name:string, abbreviation:string }>
 *         List of departments assigned to the user.
 */
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
/**
 * Map of sortable fields to their corresponding SQL expressions or aliases.
 * Keys correspond to GET parameters, values to database columns for ORDER BY.
 *
 * @var array<string,string> $sortMap
 */
$sortMap = [
    'id' => 'u.id',
    'Email' => 'u.email',
    'First_Name' => 'u.first_name',
    'Last_Name' => 'u.last_name',
    'Department' => 'd.department_name',
    'Status' => 'u.status',
];

/**
 * Default sort column and direction.
 *
 * @var string $sortBy  Column key from $sortMap; defaults to 'id'.
 * @var string $sortDir Sorting direction; 'asc' or 'desc'. Defaults to 'asc'.
 */
$sortBy = 'id';
$sortDir = 'asc';

/**
 * Type of date filter selected by the user (e.g., 'mdy', 'month_year', 'year').
 *
 * Taken from GET parameter 'date_filter_type'.
 *
 * @var string $dateFilterType
 */
$dateFilterType = $_GET['date_filter_type'] ?? '';

/**
 * Flag indicating if the filter form has been submitted.
 *
 * @var bool $isFiltered
 */
$isFiltered = isset($_GET['apply-filters']);

/**
 * Check for existence of `created_at` column in the `users` table.
 * If missing and the current database user has ALTER privileges,
 * attempt to add the column and backfill existing records.
 *
 * @var bool $hasCreatedAt True if the column exists or was added successfully.
 */
try {
    $columnCheckStmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'created_at'");
    $hasCreatedAt = $columnCheckStmt->rowCount() > 0;
    echo "<!-- DEBUG: created_at column exists: " . ($hasCreatedAt ? "Yes" : "No") . " -->";
    
    // If the column doesn't exist, try to create it
    if (!$hasCreatedAt) {
        // First check if we have ALTER privilege
        $canAlter = false;
        try {
            $privCheckStmt = $pdo->query("SHOW GRANTS FOR CURRENT_USER()");
            while ($row = $privCheckStmt->fetch(PDO::FETCH_ASSOC)) {
                $grant = array_values($row)[0];
                if (strpos($grant, 'ALL PRIVILEGES') !== false || 
                    strpos($grant, 'ALTER') !== false) {
                    $canAlter = true;
                    break;
                }
            }
        } catch (PDOException $e) {
            // Can't check privileges, assume we don't have them
            $canAlter = false;
        }
        
        // If we have ALTER privilege, try to add the column
        if ($canAlter) {
            try {
                $pdo->exec("ALTER TABLE users ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
                // Update all existing records to have a created_at value
                $pdo->exec("UPDATE users SET created_at = CURRENT_TIMESTAMP WHERE created_at IS NULL");
                $hasCreatedAt = true;
                echo "<!-- DEBUG: created_at column was added successfully -->";
            } catch (PDOException $e) {
                echo "<!-- DEBUG: Failed to add created_at column: " . htmlspecialchars($e->getMessage()) . " -->";
            }
        }
    }
} catch (PDOException $e) {
    echo "<!-- DEBUG: Error checking created_at column: " . htmlspecialchars($e->getMessage()) . " -->";
    $hasCreatedAt = false;
}

/**
 * Construct the base SQL query to fetch user details, joined with
 * departments and roles, applying `is_disabled = 0` and dynamic filters.
 *
 * Uses GROUP_CONCAT to aggregate multiple departments and roles per user in one row.
 *
 * @var string $sql     The SELECT portion of the query (filters appended later).
 * @var array  $params  Parameters to bind for filtering (initially empty).
 */
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
  " . ($hasCreatedAt ? "u.created_at," : "") . "
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
";


$params = []; // Holds bound parameters for filtering

// Only apply filters if the filter button was clicked
if ($isFiltered) {
// Department filter
if (!empty($_GET['department']) && $_GET['department'] !== 'all') {
    $sql .= " AND d.department_name = :department";
    $params[':department'] = $_GET['department'];
}

// Search filter
if (!empty($_GET['search'])) {
    $searchParam = '%' . $_GET['search'] . '%';
    $sql .= " AND (u.email LIKE :search_email OR u.username LIKE :search_username OR u.first_name LIKE :search_fname OR u.last_name LIKE :search_lname OR d.department_name LIKE :search_dept OR r.role_name LIKE :search_role)";
    $params[':search_email'] = $searchParam;
    $params[':search_username'] = $searchParam;
    $params[':search_fname'] = $searchParam;
    $params[':search_lname'] = $searchParam;
    $params[':search_dept'] = $searchParam;
    $params[':search_role'] = $searchParam;
}

    // Only apply date filters if created_at column exists
    if ($hasCreatedAt) {
// Date filters
if ($dateFilterType === 'mdy') {
    if (!empty($_GET['date_from'])) {
        $sql .= " AND DATE(u.created_at) >= :date_from";
        $params[':date_from'] = $_GET['date_from'];
    }
    if (!empty($_GET['date_to'])) {
        $sql .= " AND DATE(u.created_at) <= :date_to";
        $params[':date_to'] = $_GET['date_to'];
    }    
}

// For month-year filter
if ($dateFilterType === 'month_year') {
    if (!empty($_GET['month_year_from'])) {
        // Add debug output to see the actual value being passed
        echo "<!-- DEBUG: month_year_from value: " . htmlspecialchars($_GET['month_year_from']) . " -->";
        $monthYearFrom = $_GET['month_year_from'];
        // Ensure proper format with day component (first day of month)
        $monthYearFromFormatted = date('Y-m-01', strtotime($monthYearFrom));
        $sql .= " AND u.created_at >= :month_year_from";
        $params[':month_year_from'] = $monthYearFromFormatted;
        echo "<!-- DEBUG: Formatted month_year_from: " . $monthYearFromFormatted . " -->";
    }
    
    if (!empty($_GET['month_year_to'])) {
        // Add debug output to see the actual value being passed
        echo "<!-- DEBUG: month_year_to value: " . htmlspecialchars($_GET['month_year_to']) . " -->";
        $monthYearTo = $_GET['month_year_to'];
        // Get the last day of the selected month
        $lastDay = date('t', strtotime($monthYearTo)); // t returns the number of days in the month
        $monthYearToFormatted = date('Y-m-' . $lastDay, strtotime($monthYearTo));
        $sql .= " AND u.created_at <= :month_year_to";
        $params[':month_year_to'] = $monthYearToFormatted;
        echo "<!-- DEBUG: Formatted month_year_to: " . $monthYearToFormatted . " -->";
    }
}

// Year filter
if ($dateFilterType === 'year') {
    if (!empty($_GET['year_from'])) {
        $sql .= " AND YEAR(u.created_at) >= :year_from";
        $params[':year_from'] = $_GET['year_from'];
    }
    if (!empty($_GET['year_to'])) {
        $sql .= " AND YEAR(u.created_at) <= :year_to";
        $params[':year_to'] = $_GET['year_to'];
    }
}
    } else if ($dateFilterType) {
        // If date filter is selected but created_at doesn't exist, show a warning
        echo "<div class='alert alert-warning'>
            <h5><i class='bi bi-exclamation-triangle'></i> Date Filtering Unavailable</h5>
            <p>The <code>created_at</code> column required for date filtering does not exist in the users table.</p>
            <p>Please contact your database administrator to add this column or try using other filter options.</p>
        </div>";
    }
}

// Complete the query - Simplified GROUP BY to avoid SQL errors
$groupByClause = "GROUP BY u.id";

/**
 * Use GROUP BY on user ID to avoid SQL errors when using GROUP_CONCAT.
 * Sort by user ID descending by default.
 */
$sql .= "
$groupByClause
ORDER BY u.id DESC
";

try {
    /**
     * Debug output of the final SQL and bound parameters.
     */
    echo "<!-- DEBUG SQL: " . htmlspecialchars($sql) . " -->\n";
    echo "<!-- DEBUG PARAMS: " . htmlspecialchars(print_r($params, true)) . " -->\n";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    /**
     * Array of fetched user records; each element is an associative array
     * containing user fields and concatenated department/role strings.
     *
     * @var array<int,array<string,mixed>> $users
     */
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
    // Debug user count
    echo "<!-- DEBUG USERS COUNT: " . count($users) . " -->\n";
} catch (PDOException $e) {
    /**
     * If a database error occurs, display an alert with the SQL and error message,
     * and log the error. Continue rendering the page with an empty user list.
     */
    echo "<div class='alert alert-danger'>";
    echo "<h4>Database Error:</h4>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>SQL: " . htmlspecialchars($sql) . "</p>";
    echo "</div>";
    error_log('User fetch error: ' . $e->getMessage());
    // Continue execution to show the page with error message
    $users = [];
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
    <!-- Date Filter Handler JS -->
    <script src="<?php echo BASE_URL; ?>src/control/js/date_filter_handler.js" defer></script>

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

        /* New responsive modal styles */
        .modal-dialog {
            max-width: 90%;
            margin: 1.75rem auto;
            max-height: calc(100vh - 3.5rem);
            display: flex;
            flex-direction: column;
        }

        .modal-content {
            max-height: calc(100vh - 3.5rem);
            display: flex;
            flex-direction: column;
        }

        .modal-body {
            overflow-y: auto;
            padding: 1rem;
            flex: 1;
        }

        .department-table-container {
            max-height: 200px;
            overflow-y: auto;
            margin-bottom: 1rem;
        }

        @media (min-width: 576px) {
            .modal-dialog {
                max-width: 500px;
            }
        }

        @media (min-width: 768px) {
            .modal-dialog {
                max-width: 600px;
            }
        }

        @media (min-width: 992px) {
            .modal-dialog {
                max-width: 700px;
            }
        }

        @media (min-width: 1200px) {
            .modal-dialog {
                max-width: 800px;
            }
        }

        /* Make select2 stay behind modal when modal is open */
        body.modal-open .select2-container--open {
            z-index: 1000 !important; /* Lower than modal */
        }
        
        /* Only for page select2 elements (not in modal) */
        body.modal-open .select2-dropdown:not(.select2-dropdown--below) {
            z-index: 1000 !important; /* Lower than modal */
        }
        
        /* For select2 elements inside modals */
        .modal .select2-dropdown {
            z-index: 1056 !important; /* Higher than modal */
        }
        
        /* Ensure modal backdrop is behind modal but above other elements */
        .modal-backdrop {
            z-index: 1054 !important;
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

        /* Filter form styling */
        #userFilterForm {
            background-color: #f8f9fa;
            border-radius: 0.25rem;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }

        #userFilterForm .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        #userFilterForm .input-group-text {
            background-color: #e9ecef;
        }

        .action-buttons {
            margin-top: 1rem;
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        /* Make the filters container responsive */
        .filters-container {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        @media (min-width: 768px) {
            .filters-container {
                flex-direction: row;
                align-items: flex-start;
            }
        }
        
        /* Additional filter form styling from equipment_details.php */
        #userFilterForm .row {
            margin-bottom: 10px;
        }
        
        #dateInputsContainer {
            padding-top: 10px;
            padding-bottom: 10px;
            border-top: 1px solid #e9ecef;
        }
        
        /* Ensure Select2 input matches form-control size and font */
        .select2-container--default .select2-selection--single {
            height: 38px !important;
            padding: 6px 12px;
            font-size: 1rem;
            border: 1px solid #ced4da;
            border-radius: 0.375rem;
            background-color: #fff;
            box-shadow: none;
            display: flex;
            align-items: center;
        }

        .select2-container .select2-selection--single .select2-selection__rendered {
            line-height: 24px;
            color: #212529;
            padding-left: 0;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px;
            right: 10px;
        }

        .select2-container--open .select2-dropdown {
            z-index: 9999 !important;
        }
        
        /* Make Select2 match Bootstrap form-control height */
        .select2-container .select2-selection {
            min-height: 38px !important;
        }
        
        /* Fix padding for the select2 input */
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            padding-top: 2px;
        }
        
        /* Adjust the clear button position */
        .select2-container--default .select2-selection--single .select2-selection__clear {
            margin-right: 20px;
        }
        
        /* Make the dropdown match Bootstrap styling */
        .select2-dropdown {
            border-color: #ced4da;
            border-radius: 0.375rem;
        }
        
        /* Make the search field match Bootstrap input */
        .select2-container--default .select2-search--dropdown .select2-search__field {
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
            padding: 0.375rem 0.75rem;
        }

        .sortable {
            cursor: pointer;
            position: relative;
            padding-right: 20px !important;
        }

        .sortable:hover {
            background-color: #f8f9fa;
        }

        .sortable i {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }

        .sortable:hover i {
            color: #0d6efd;
        }

        /* For select2 elements inside modals */
        .modal .select2-dropdown {
            z-index: 1056 !important; /* Higher than modal */
        }
        
        /* Ensure modal backdrop is behind modal but above other elements */
        .modal-backdrop {
            z-index: 1054 !important;
        }
        
        /* Fix for Select2 dropdowns in modals */
        .modal .select2-container--open .select2-dropdown {
            z-index: 2060 !important; /* Even higher than modal content */
        }
        
        /* Ensure Select2 dropdowns in the main document stay behind modals */
        body.modal-open > .select2-container--open .select2-dropdown {
            z-index: 1040 !important; /* Lower than modal backdrop */
        }
        
        /* Force all select2 dropdowns to stay behind modal when modal is open */
        body.modal-open .select2-container {
            z-index: 1040 !important; /* Lower than modal backdrop */
        }
        
        /* Improve modal positioning */
        .modal-dialog {
            display: flex;
            align-items: center;
            min-height: calc(100% - 3.5rem);
            margin: 1.75rem auto;
        }
        
        .modal-lower {
            margin-top: 5rem;
        }
        
        /* Ensure modals are centered on mobile too */
        @media (max-width: 576px) {
            .modal-dialog {
                min-height: calc(100% - 1rem);
                margin: 0.5rem auto;
            }
        }
        
        /* Additional Select2 fixes for modals */
        .modal-open .select2-container--open {
            z-index: 1 !important; /* Force below modal */
        }

        /* Add this to prevent dropdowns from showing through modal */
        body.modal-open .select2-container--open {
            z-index: 1039 !important; /* Lower than modal backdrop */
        }
        
        .modal-backdrop {
            z-index: 1040 !important; /* Ensure backdrop covers everything */
        }
        
        .modal {
            z-index: 1050 !important; /* Higher than backdrop */
        }
        
        /* Fix for Select2 elements within modal */
        .modal .select2-container--open {
            z-index: 1060 !important; /* Higher than modal content */
        }
    </style>
</head>

<body>
    <div class="main-content container-fluid">
        <header>
            <h1>USER MANAGER</h1>
        </header>
 
            <!-- Enhanced Filter Section -->
                        <!-- Enhanced Filter Section -->
                        <form method="GET" class="row g-3 mb-4" id="userFilterForm">
                <div class="card-body">
                    <div class="container-fluid px-0">
                        <div class="row g-3">
                            <!-- Create button column -->
                            <div class="col">
                                <label class="form-label d-none d-md-block">&nbsp;</label>
                                <?php if ($canCreate): ?>
                                    <button type="button" id="create-btn" class="btn btn-dark w-100">
                                        <i class="bi bi-plus-lg"></i> Create New User
                                    </button>
                                <?php endif; ?>
                            </div>
                            <!-- Department filter -->
                            <div class="col">
                                <label for="department-filter" class="form-label">Department</label>
                                <select class="form-select" name="department" id="department-filter" autocomplete="off">
                                    <option value="all">All Departments</option>
                                    <?php
                                    try {
                                        $deptStmt = $pdo->query("SELECT department_name, abbreviation FROM departments WHERE is_disabled = 0 ORDER BY department_name");
                                        $allDepartments = $deptStmt->fetchAll(PDO::FETCH_ASSOC);

                                        foreach ($allDepartments as $dept) {
                                            $name = htmlspecialchars($dept['department_name']);
                                            $abbreviation = htmlspecialchars($dept['abbreviation']);
                                            $label = "($abbreviation) $name";
                                            $selected = (isset($_GET['department']) && $_GET['department'] === $name) ? 'selected' : '';
                                            echo '<option value="' . $name . '" ' . $selected . '>' . $label . '</option>';
                                        }
                                    } catch (PDOException $e) {
                                        // fallback: empty
                                    }
                                    ?>
                                </select>
                            </div>

                            <!-- Date Filter Type -->
                            <div class="col">
                                <label class="form-label fw-semibold">Date Filter Type</label>
                                <select id="dateFilterType" name="date_filter_type" class="form-select shadow-sm">
                                    <option value="">-- Select Type --</option>
                                    <option value="month_year" <?= (($_GET['date_filter_type'] ?? '') === 'month_year') ? 'selected' : '' ?>>Month-Year Range</option>
                                    <option value="year" <?= (($_GET['date_filter_type'] ?? '') === 'year') ? 'selected' : '' ?>>Year Range</option>
                                    <option value="mdy" <?= (($_GET['date_filter_type'] ?? '') === 'mdy') ? 'selected' : '' ?>>Month-Date-Year Range</option>
                                </select>
                            </div>

                            <!-- Search bar -->
                            <div class="col">
                                <label class="form-label fw-semibold">Search</label>
                                <div class="input-group">
                                    <input type="text" name="search" id="search-filters" class="form-control" placeholder="Search keyword..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                </div>
                            </div>

                            <!-- Filter and Clear buttons -->
                            <div class="col">
                                <div class="d-flex gap-2">
                                    <div class="flex-grow-1">
                                        <label class="form-label d-none d-md-block">&nbsp;</label>
                                        <button type="submit" id="apply-filters" name="apply-filters" class="btn btn-dark w-100">
                                            <i class="bi bi-funnel"></i> Filter
                                        </button>
                                    </div>
                                    <div class="flex-grow-1">
                                        <label class="form-label d-none d-md-block">&nbsp;</label>
                                        <a href="<?= $_SERVER['PHP_SELF'] ?>" id="clear-filters-btn" class="btn btn-secondary w-100">
                                            <i class="bi bi-x-circle"></i> Clear
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <!-- Manage Roles button -->
                            <div class="col">
 <?php if ($rbac->hasPrivilege('User Management', 'Modify')): ?>
                                <label class="form-label d-none d-md-block">&nbsp;</label>
                                <a href="user_roles_management.php" class="btn btn-primary w-100">
                                    <i class="bi bi-person-gear"></i>  Manage User Roles
                                </a>
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

                        <!-- Date filter fields container -->
                        <div id="dateInputsContainer" class="row g-3 mt-2 <?= !empty($_GET['date_filter_type']) ? '' : 'd-none' ?>">
                            <!-- MDY Range -->
                            <div class="col-md-6 date-filter date-mdy <?= (($_GET['date_filter_type'] ?? '') === 'mdy') ? '' : 'd-none' ?>">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Date From</label>
                                        <input type="date" name="date_from" class="form-control shadow-sm"
                                            value="<?= htmlspecialchars($_GET['date_from'] ?? '') ?>"
                                            placeholder="Start Date (YYYY-MM-DD)">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Date To</label>
                                        <input type="date" name="date_to" class="form-control shadow-sm"
                                            value="<?= htmlspecialchars($_GET['date_to'] ?? '') ?>"
                                            placeholder="End Date (YYYY-MM-DD)">
                                    </div>
                                </div>
                            </div>

                            <!-- Year Range -->
                            <div class="col-md-6 date-filter date-year <?= (($_GET['date_filter_type'] ?? '') === 'year') ? '' : 'd-none' ?>">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Year From</label>
                                        <input type="number" name="year_from" class="form-control shadow-sm"
                                            min="1900" max="2100"
                                            placeholder="e.g., 2023"
                                            value="<?= htmlspecialchars($_GET['year_from'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Year To</label>
                                        <input type="number" name="year_to" class="form-control shadow-sm"
                                            min="1900" max="2100"
                                            placeholder="e.g., 2025"
                                            value="<?= htmlspecialchars($_GET['year_to'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>

                            <!-- Month-Year Range -->
                            <div class="col-md-6 date-filter date-month_year <?= (($_GET['date_filter_type'] ?? '') === 'month_year') ? '' : 'd-none' ?>">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">From (MM-YYYY)</label>
                                        <input type="month" name="month_year_from" class="form-control shadow-sm"
                                            value="<?= htmlspecialchars($_GET['month_year_from'] ?? '') ?>"
                                            placeholder="e.g., 2023-01">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">To (MM-YYYY)</label>
                                        <input type="month" name="month_year_to" class="form-control shadow-sm"
                                            value="<?= htmlspecialchars($_GET['month_year_to'] ?? '') ?>"
                                            placeholder="e.g., 2023-12">
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </form>

        <div class="table-responsive" id="table">
            <table class="table table-striped table-hover" id="umTable">
                <thead>
                    <tr>
                        <!-- <th><input type="checkbox" id="select-all"></th> -->
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
                                <!-- <td>
                                    <input type="checkbox"
                                        class="select-row"
                                        value="<?= htmlspecialchars((string)$u['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                </td> -->
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
                <div class="row align-items-center g-3 mb-3">
                    <div class="col-12 col-md-4">
                        <div class="text-muted">
                            <?php $totalUsers = count($users); ?>
                            <input type="hidden" id="total-users" value="<?= $totalUsers ?>">
                            Showing <span id="currentPage">1</span> to <span id="rowsPerPage"><?= min($totalUsers, 10) ?></span> of <span id="totalRows"><?= $totalUsers ?></span> entries
                        </div>
                    </div>
                    <div class="col-12 col-md-4 text-center">
                        <nav aria-label="Page navigation">
                            <ul class="pagination pagination-sm d-inline-flex justify-content-center mb-0" id="pagination"></ul>
                        </nav>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="d-flex align-items-center gap-2 justify-content-md-end">
                            <button id="prevPage" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1">
                                <i class="bi bi-chevron-left"></i> Previous
                            </button>
                            <select id="rowsPerPageSelect" class="form-select form-select-sm" style="width: auto;">
                                <option value="10" selected>10</option>
                                <option value="20">20</option>
                                <option value="30">30</option>
                                <option value="50">50</option>
                            </select>
                            <button id="nextPage" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1">
                                Next <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
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
        aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
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

    <!-- Add Toast notification container -->
    <div class="toast-container position-fixed top-0 end-0 p-3">
        <!-- Toasts will be inserted here dynamically -->
    </div>

    <script>
        $(document).ready(function() {
            // Adjust z-index of Select2 dropdowns when modal opens/closes
            $(document).on('show.bs.modal', '.modal', function() {
                // Lower z-index for all page-level Select2 dropdowns
                $('.select2-container--default:not(.select2-container--open)').css('z-index', '1000');
                
                // Close any open Select2 dropdowns on the page
                $('.select2-container--open').removeClass('select2-container--open');
                
                // Force close any open Select2 dropdowns
                if ($.fn.select2) {
                    $('select.select2-hidden-accessible').select2('close');
                }
                
                // Hide any visible Select2 dropdowns
                $('.select2-dropdown').hide();
            });
            
            $(document).on('hide.bs.modal', '.modal', function() {
                // Restore z-index for all Select2 dropdowns
                setTimeout(function() {
                    $('.select2-container--default').css('z-index', '9999');
                }, 100);
            });
            
            // Create button handler - show the create user modal
            $('#create-btn').on('click', function() {
                // Reset the form first
                $('#createUserForm')[0].reset();
                
                // Close any open Select2 dropdowns
                if ($.fn.select2) {
                    $('select.select2-hidden-accessible').select2('close');
                }
                
                // Clear any previously selected departments
                selectedDepartments = [];
                $('#createAssignedDepartmentsTable tbody').empty();
                
                // Show the modal
                const modal = new bootstrap.Modal(document.getElementById('createUserModal'), {
                    backdrop: 'static',
                    keyboard: false
                });
                modal.show();
                
                // Ensure modal is centered
                setTimeout(function() {
                    const modalDialog = $('#createUserModal .modal-dialog');
                    const windowHeight = $(window).height();
                    const modalHeight = modalDialog.height();
                    const topMargin = Math.max(0, (windowHeight - modalHeight) / 2);
                    modalDialog.css('margin-top', topMargin + 'px');
                }, 200);
            });
            
            // Initialize pagination
            if (typeof initPagination === 'function') {
                console.log("Initializing pagination for user management");
                window.paginationConfig = {
                    tableId: 'umTableBody',
                    rowsPerPageSelectId: 'rowsPerPageSelect',
                    currentPageId: 'currentPage',
                    rowsPerPageId: 'rowsPerPage',
                    totalRowsId: 'totalRows',
                    prevPageId: 'prevPage',
                    nextPageId: 'nextPage',
                    paginationId: 'pagination',
                    currentPage: 1
                };
                
                // Initialize event listeners for pagination buttons
                document.getElementById('prevPage').addEventListener('click', function(e) {
                    e.preventDefault();
                    if (window.paginationConfig.currentPage > 1) {
                        window.paginationConfig.currentPage--;
                        window.updatePagination();
                    }
                });
                
                document.getElementById('nextPage').addEventListener('click', function(e) {
                    e.preventDefault();
                    const rowsPerPage = parseInt(document.getElementById('rowsPerPageSelect').value) || 10;
                    const totalPages = Math.ceil(window.filteredRows.length / rowsPerPage);
                    if (window.paginationConfig.currentPage < totalPages) {
                        window.paginationConfig.currentPage++;
                        window.updatePagination();
                    }
                });
                
                // Listen for rows per page changes
                document.getElementById('rowsPerPageSelect').addEventListener('change', function() {
                    window.paginationConfig.currentPage = 1; // Reset to first page
                    window.updatePagination();
                });
            }

            const originalUpdatePagination = window.updatePagination || function() {};

            // Function to check and update pagination visibility
            window.forcePaginationCheck = function() {
                const totalRows = window.filteredRows ? window.filteredRows.length : 0;
                const rowsPerPage = parseInt(document.getElementById('rowsPerPageSelect').value) || 10;
                const totalPages = Math.ceil(totalRows / rowsPerPage);
                const currentPage = window.paginationConfig ? window.paginationConfig.currentPage : 1;
                
                const prevBtn = document.getElementById('prevPage');
                const nextBtn = document.getElementById('nextPage');
                const paginationEl = document.getElementById('pagination');
                const paginationContainer = paginationEl ? paginationEl.closest('.col-md-4') : null;

                // Hide pagination completely if all rows fit on one page
                if (totalRows <= rowsPerPage) {
                    if (prevBtn) prevBtn.style.display = 'none';
                    if (nextBtn) nextBtn.style.display = 'none';
                    if (paginationContainer) paginationContainer.style.display = 'none';
                } else {
                    // Show pagination but conditionally hide prev/next buttons
                    if (paginationContainer) paginationContainer.style.display = '';

                    if (prevBtn) {
                        prevBtn.style.display = '';
                        if (currentPage <= 1) {
                            prevBtn.classList.add('disabled');
                        } else {
                            prevBtn.classList.remove('disabled');
                        }
                    }

                    if (nextBtn) {
                        nextBtn.style.display = '';
                        if (currentPage >= totalPages) {
                            nextBtn.classList.add('disabled');
                        } else {
                            nextBtn.classList.remove('disabled');
                        }
                    }
                }
                
                // Update the showing X to Y of Z entries text
                const currentPageEl = document.getElementById('currentPage');
                const rowsPerPageEl = document.getElementById('rowsPerPage');
                
                if (currentPageEl && rowsPerPageEl) {
                    const start = totalRows === 0 ? 0 : (currentPage - 1) * rowsPerPage + 1;
                    const end = Math.min(start + rowsPerPage - 1, totalRows);
                    
                    currentPageEl.textContent = start;
                    rowsPerPageEl.textContent = end;
                }
            }

            window.updatePagination = function() {
                // Get all rows again in case the DOM was updated
                window.allRows = Array.from(document.querySelectorAll('#umTableBody tr'));

                // If filtered rows is empty or not defined, use all rows
                if (!window.filteredRows || window.filteredRows.length === 0) {
                    window.filteredRows = window.allRows;
                }

                // Update total rows display
                const totalRowsEl = document.getElementById('totalRows');
                if (totalRowsEl) {
                    totalRowsEl.textContent = window.filteredRows.length;
                }

                // Get pagination elements
                const rowsPerPage = parseInt(document.getElementById('rowsPerPageSelect').value) || 10;
                const totalRows = window.filteredRows.length;
                const totalPages = Math.ceil(totalRows / rowsPerPage);
                const currentPage = window.paginationConfig ? window.paginationConfig.currentPage : 1;

                // Update rows per page display
                const rowsPerPageEl = document.getElementById('rowsPerPage');
                if (rowsPerPageEl) {
                    const visibleRows = Math.min(rowsPerPage, window.filteredRows.length - (currentPage - 1) * rowsPerPage);
                    rowsPerPageEl.textContent = visibleRows;
                }
                
                // Update current page display
                const currentPageEl = document.getElementById('currentPage');
                if (currentPageEl) {
                    const start = totalRows === 0 ? 0 : (currentPage - 1) * rowsPerPage + 1;
                    currentPageEl.textContent = start;
                }
                
                // Generate pagination numbers
                const paginationEl = document.getElementById('pagination');
                if (paginationEl) {
                    paginationEl.innerHTML = '';
                    
                    // Previous button
                    const prevLi = document.createElement('li');
                    prevLi.className = 'page-item' + (currentPage <= 1 ? ' disabled' : '');
                    const prevLink = document.createElement('a');
                    prevLink.className = 'page-link';
                    prevLink.href = '#';
                    prevLink.innerHTML = '<i class="bi bi-chevron-left"></i>';
                    prevLink.setAttribute('aria-label', 'Previous');
                    prevLink.addEventListener('click', function(e) {
                        e.preventDefault();
                        if (currentPage > 1) {
                            window.paginationConfig.currentPage--;
                            window.updatePagination();
                        }
                    });
                    prevLi.appendChild(prevLink);
                    paginationEl.appendChild(prevLi);
                    
                    // Calculate range of page numbers to show
                    let startPage = Math.max(1, currentPage - 1);
                    let endPage = Math.min(totalPages, startPage + 2);
                    
                    // Adjust if we're near the end
                    if (endPage - startPage < 2 && startPage > 1) {
                        startPage = Math.max(1, endPage - 2);
                    }
                    
                    // First page if not in range
                    if (startPage > 1) {
                        const firstLi = document.createElement('li');
                        firstLi.className = 'page-item';
                        const firstLink = document.createElement('a');
                        firstLink.className = 'page-link';
                        firstLink.href = '#';
                        firstLink.textContent = '1';
                        firstLink.addEventListener('click', function(e) {
                            e.preventDefault();
                            window.paginationConfig.currentPage = 1;
                            window.updatePagination();
                        });
                        firstLi.appendChild(firstLink);
                        paginationEl.appendChild(firstLi);
                        
                        // Add ellipsis if needed
                        if (startPage > 2) {
                            const ellipsisLi = document.createElement('li');
                            ellipsisLi.className = 'page-item disabled';
                            const ellipsisSpan = document.createElement('span');
                            ellipsisSpan.className = 'page-link';
                            ellipsisSpan.textContent = '...';
                            ellipsisLi.appendChild(ellipsisSpan);
                            paginationEl.appendChild(ellipsisLi);
                        }
                    }
                    
                    // Page numbers
                    for (let i = startPage; i <= endPage; i++) {
                        const pageLi = document.createElement('li');
                        pageLi.className = 'page-item' + (i === currentPage ? ' active' : '');
                        const pageLink = document.createElement('a');
                        pageLink.className = 'page-link';
                        pageLink.href = '#';
                        pageLink.textContent = i;
                        pageLink.addEventListener('click', function(e) {
                            e.preventDefault();
                            window.paginationConfig.currentPage = i;
                            window.updatePagination();
                        });
                        pageLi.appendChild(pageLink);
                        paginationEl.appendChild(pageLi);
                    }
                    
                    // Last page if not in range
                    if (endPage < totalPages) {
                        // Add ellipsis if needed
                        if (endPage < totalPages - 1) {
                            const ellipsisLi = document.createElement('li');
                            ellipsisLi.className = 'page-item disabled';
                            const ellipsisSpan = document.createElement('span');
                            ellipsisSpan.className = 'page-link';
                            ellipsisSpan.textContent = '...';
                            ellipsisLi.appendChild(ellipsisSpan);
                            paginationEl.appendChild(ellipsisLi);
                        }
                        
                        const lastLi = document.createElement('li');
                        lastLi.className = 'page-item';
                        const lastLink = document.createElement('a');
                        lastLink.className = 'page-link';
                        lastLink.href = '#';
                        lastLink.textContent = totalPages;
                        lastLink.addEventListener('click', function(e) {
                            e.preventDefault();
                            window.paginationConfig.currentPage = totalPages;
                            window.updatePagination();
                        });
                        lastLi.appendChild(lastLink);
                        paginationEl.appendChild(lastLi);
                    }
                    
                    // Next button
                    const nextLi = document.createElement('li');
                    nextLi.className = 'page-item' + (currentPage >= totalPages ? ' disabled' : '');
                    const nextLink = document.createElement('a');
                    nextLink.className = 'page-link';
                    nextLink.href = '#';
                    nextLink.innerHTML = '<i class="bi bi-chevron-right"></i>';
                    nextLink.setAttribute('aria-label', 'Next');
                    nextLink.addEventListener('click', function(e) {
                        e.preventDefault();
                        if (currentPage < totalPages) {
                            window.paginationConfig.currentPage++;
                            window.updatePagination();
                        }
                    });
                    nextLi.appendChild(nextLink);
                    paginationEl.appendChild(nextLi);
                }

                // Update visibility of rows
                window.filteredRows.forEach(function(row, index) {
                    const pageIndex = Math.floor(index / rowsPerPage);
                    if (pageIndex === currentPage - 1) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });

                // Call original updatePagination if it exists
                if (typeof originalUpdatePagination === 'function') {
                originalUpdatePagination();
                }
                
                // Update prev/next button visibility
                forcePaginationCheck();
            };

            // Call updatePagination immediately
            updatePagination();
            
            // Initialize Select2 for department filter with custom positioning
            $('#department-filter').select2({
                placeholder: 'All Departments',
                allowClear: true,
                minimumResultsForSearch: 5,
                dropdownParent: $('body'), // Attach to body for proper z-index handling
                closeOnSelect: true,
                selectOnClose: true
            }).on('select2:open', function() {
                // Check if any modal is open
                if ($('.modal.show').length) {
                    // If a modal is open, close the dropdown
                    $(this).select2('close');
                    return false;
                }
            });
            
            // Initialize Select2 for date filter type dropdown - ONLY ONCE
            if ($.fn.select2 && $('#dateFilterType').length && !$('#dateFilterType').hasClass('select2-hidden-accessible')) {
                console.log('Initializing Select2 for dateFilterType');
                $('#dateFilterType').select2({
                    placeholder: '-- Select Type --',
                    allowClear: true,
                    width: '100%',
                    minimumResultsForSearch: -1 // Hide search box
                }).on('select2:select', function(e) {
                    console.log('Date filter type selected:', e.params.data.id);
                    handleDateFilterTypeChange(e.params.data.id);
                }).on('select2:clear', function() {
                    console.log('Date filter type cleared');
                    handleDateFilterTypeChange('');
                }).on('select2:open', function() {
                    // Check if any modal is open
                    if ($('.modal.show').length) {
                        // If a modal is open, close the dropdown
                        $(this).select2('close');
                        return false;
                    }
                });
            }
            
            // Function to handle date filter type changes
            function handleDateFilterTypeChange(selectedType) {
                console.log('Handling date filter type change:', selectedType);
                
                // Hide all date filter containers first
                    $('.date-filter').addClass('d-none');
                
                // Hide the container if no filter type selected
                if (!selectedType) {
                    $('#dateInputsContainer').addClass('d-none');
                    return;
                }
                
                // Show the container and the specific filter type fields
                $('#dateInputsContainer').removeClass('d-none');
                
                // Make sure to use the correct selector
                console.log('Looking for element with class: date-' + selectedType);
                $('.date-' + selectedType).removeClass('d-none');
            }
            
            // Initialize date filter UI based on current selection
            const currentValue = $('#dateFilterType').val();
            if (currentValue) {
                console.log('Initial date filter type:', currentValue);
                handleDateFilterTypeChange(currentValue);
            }

            // Add a direct change handler (in addition to the Select2 events)
            $('#dateFilterType').on('change', function() {
                const selectedType = $(this).val();
                console.log('Date filter type changed via regular change event:', selectedType);
                handleDateFilterTypeChange(selectedType);
                
                // Clear any existing validation errors when changing filter type
                $('#filterError').remove();
            });
            
            // Handle the clear filters button
            $('#clear-filters-btn').on('click', function() {
                // Reset the date filter type dropdown
                if ($('#dateFilterType').hasClass('select2-hidden-accessible')) {
                    $('#dateFilterType').val('').trigger('change.select2').trigger('change');
                            } else {
                    $('#dateFilterType').val('').trigger('change');
                }
                
                // Hide all date filter containers
                $('#dateInputsContainer').addClass('d-none');
                $('.date-filter').addClass('d-none');
                $('#filterError').remove();
            });

            // Enhanced validation for date filters
            var form = $('#userFilterForm');
            form.on('submit', function(e) {
                let isValid = true;
                let errorMessage = '';
                let selectedType = $('#dateFilterType').val();
                
                if (selectedType) {
                    let fromDate, toDate;
                    
                    if (selectedType === 'mdy') {
                        fromDate = $('input[name="date_from"]').val();
                        toDate = $('input[name="date_to"]').val();
                        
                        // Check if dates are empty
                        if (fromDate && !toDate) {
                            isValid = false;
                            errorMessage = 'Please select both From and To dates.';
                        } else if (!fromDate && toDate) {
                            isValid = false;
                            errorMessage = 'Please select both From and To dates.';
                        } else if (fromDate && toDate) {
                            // Check if from date is greater than to date
                            if (new Date(fromDate) > new Date(toDate)) {
                                isValid = false;
                                errorMessage = 'From date cannot be greater than To date.';
                            }
                        }
                    } else if (selectedType === 'month_year') {
                        fromDate = $('input[name="month_year_from"]').val();
                        toDate = $('input[name="month_year_to"]').val();
                        
                        // Check if dates are empty
                        if (fromDate && !toDate) {
                            isValid = false;
                            errorMessage = 'Please select both From and To dates.';
                        } else if (!fromDate && toDate) {
                            isValid = false;
                            errorMessage = 'Please select both From and To dates.';
                        } else if (fromDate && toDate) {
                            // Check if from date is greater than to date
                            if (new Date(fromDate) > new Date(toDate)) {
                                isValid = false;
                                errorMessage = 'From date cannot be greater than To date.';
                            }
                        }
                    } else if (selectedType === 'year') {
                        fromDate = $('input[name="year_from"]').val();
                        toDate = $('input[name="year_to"]').val();
                        
                        // Check if years are empty
                        if (fromDate && !toDate) {
                            isValid = false;
                            errorMessage = 'Please enter both From and To years.';
                        } else if (!fromDate && toDate) {
                            isValid = false;
                            errorMessage = 'Please enter both From and To years.';
                        } else if (fromDate && toDate) {
                            // Check if from year is greater than to year
                            if (parseInt(fromDate) > parseInt(toDate)) {
                                isValid = false;
                                errorMessage = 'From year cannot be greater than To year.';
                            }
                        }
                    }
                }
                
                if (!isValid) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    $('#filterError').remove();
                    $('#dateInputsContainer').css('position', 'relative');
                    $('#dateInputsContainer').append('<div id="filterError" class="validation-tooltip" style="position: absolute; top: 100%; left: 50%; transform: translateX(-50%); background-color: #d9534f; color: white; padding: 6px 10px; border-radius: 4px; font-size: 0.85em; z-index: 1000; margin-top: 5px; white-space: nowrap; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">' + errorMessage + '<div style="position: absolute; top: -5px; left: 50%; transform: translateX(-50%); width: 0; height: 0; border-left: 5px solid transparent; border-right: 5px solid transparent; border-bottom: 5px solid #d9534f;"></div></div>');
                    setTimeout(function() {
                        $('#filterError').fadeOut('slow', function() {
                            $(this).remove();
                        });
                    }, 3000);
                    return false;
                }
                $('#filterError').remove();
                return true;
            });
            
            // Apply filter button handler not needed anymore as we're using form submit validation

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
            $(".modal-backdrop").remove(); // Remove any existing modal backdrops
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

            // Show the modal using Bootstrap 5 modal API
            const editModal = new bootstrap.Modal(document.getElementById('editUserModal'), {
                backdrop: 'static',
                keyboard: false
            });
            editModal.show();
            
            // Ensure modal is centered
            setTimeout(function() {
                const modalDialog = $('#editUserModal .modal-dialog');
                const windowHeight = $(window).height();
                const modalHeight = modalDialog.height();
                const topMargin = Math.max(0, (windowHeight - modalHeight) / 2);
                modalDialog.css('margin-top', topMargin + 'px');
            }, 200);
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
            
            // Fix for page-level Select2 dropdowns appearing above modal
            $('.select2-dropdown').parents('body').find('> .select2-container').css('z-index', '1000');
            
            // Ensure Select2 inside modals has higher z-index
            $(this).find('.select2-container').css('z-index', '1056');
        });
        
        // Handle modal being closed - restore Select2 z-index
        $('.modal').on('hidden.bs.modal', function() {
            // Restore regular Select2 z-index
            $('.select2-container').css('z-index', '9999');
        });
        
        $('#editUserModal').on('shown.bs.modal', function() {
            // your existing Select2 width fix
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

        // Client-side filtering function
        function filterTable() {
            const searchText = $('#search-filters').val().toLowerCase();
            const deptFilter = $('#department-filter').val();

            console.log('Filtering with:', {
                searchText,
                deptFilter
            });

            // If we're using server-side filtering, don't do client-side filtering
            // This is to prevent double-filtering when the form is submitted
            if (window.location.search.includes('apply-filters=') || 
                window.location.search.includes('search=') || 
                window.location.search.includes('department=') ||
                window.location.search.includes('date_filter_type=')) {
                return;
            }

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

        // Function to refresh table data only without reloading the entire page
        function refreshUserTable(callback) {
            // Ensure any lingering modal artifacts are cleaned up before refresh
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open').css('padding-right', '');
            
            // Remove specific z-index backdrop
            $('div.modal-backdrop[style*="z-index: 1040"]').remove();
            
            // Force enable scrolling
            $('html, body').css({
                'overflow': '',
                'height': ''
            });
            
            // Store current pagination and sort state
            const currentPage = window.paginationConfig ? window.paginationConfig.currentPage : 1;
            const rowsPerPage = $('#rowsPerPageSelect').val() || 10;
            const sortColumn = currentSort || 'id';
            const sortDirection = currentSortDir || 'asc';
            
            // Load just the table content
            $('#umTable').load(location.href + ' #umTable > *', function() {
                // Ensure modal artifacts are cleaned up after refresh too
                $('.modal-backdrop').remove();
                $('body').removeClass('modal-open').css('padding-right', '');
                
                // Remove specific z-index backdrop
                $('div.modal-backdrop[style*="z-index: 1040"]').remove();
                
                // Force enable scrolling
                $('html, body').css({
                    'overflow': '',
                    'height': ''
                });
                
                // Re-initialize all rows for pagination after content is loaded
                window.allRows = Array.from(document.querySelectorAll('#umTableBody tr'));
                window.filteredRows = window.allRows;
                
                // Restore sort state
                currentSort = sortColumn;
                currentSortDir = sortDirection;
                
                // Update sort icons
                $('.sort-icon').removeClass('bi-caret-up-fill bi-caret-down-fill');
                const iconClass = currentSortDir === 'asc' ? 'bi-caret-up-fill' : 'bi-caret-down-fill';
                $(`.sort-header[data-sort="${currentSort}"] .sort-icon`).addClass(iconClass);
                
                // Re-apply any active filters
                filterTable();
                
                // Restore pagination state
                if (window.paginationConfig) {
                    window.paginationConfig.currentPage = currentPage;
                    updatePagination();
                }
                
                // Final modal cleanup check
                $('.modal-backdrop').remove();
                $('body').removeClass('modal-open').css('padding-right', '');
                
                // Rebind click events to the new elements
                rebindTableEvents();
                
                // Execute callback if provided
                if (typeof callback === 'function') {
                    callback();
                    
                    // One last check after callback executes
                    setTimeout(function() {
                        $('.modal-backdrop').remove();
                        $('body').removeClass('modal-open').css('padding-right', '');
                        
                        // Force enable scrolling
                        $('html, body').css({
                            'overflow': '',
                            'height': ''
                        });
                    }, 50);
                }
            });
        }
        
        // Function to rebind events to table elements after refresh
        function rebindTableEvents() {
            // Rebind edit buttons
            $('.edit-btn').off('click').on('click', function() {
                // ... existing edit button code ...
            });
            
            // Rebind delete buttons
            $('.delete-btn').off('click').on('click', function() {
                const userId = $(this).data('id');
                const username = $(this).closest('tr').find('td:eq(1)').text(); // Get username from the second column
                
                // Set up the confirmation modal
                $('#confirmDeleteMessage').text(`Are you sure you want to remove user "${username}"?`);
                
                // Set up the confirm button action
                $('#confirmDeleteButton').off('click').on('click', function() {
                    // First hide the modal completely and clean up
                    $('#confirmDeleteModal').modal('hide');
                    
                    // Wait for the modal to finish hiding before continuing
                    setTimeout(function() {
                        // Clean up modal artifacts
                        $('.modal-backdrop').remove();
                        $('body').removeClass('modal-open').css('padding-right', '');
                        
                        // Remove backdrop with specific z-index
                        $('div.modal-backdrop[style*="z-index: 1040"]').remove();
                        
                        // Send delete request
                        $.ajax({
                            url: 'delete_user.php',
                            type: 'POST',
                            data: {
                                user_id: userId
                            },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    Toast.success('User removed successfully');
                                    
                                    // Clean up again before refreshing table
                                    $('.modal-backdrop').remove();
                                    $('body').removeClass('modal-open').css('padding-right', '');
                                    
                                    // Manually reload the table data
                                    $.get(location.href, function(data) {
                                        // Extract only the table HTML from the response
                                        const tableHtml = $(data).find('#umTable').html();
                                        $('#umTable').html(tableHtml);
                                        
                                        // Re-initialize pagination and events
                                        window.allRows = Array.from(document.querySelectorAll('#umTableBody tr'));
                                        window.filteredRows = window.allRows;
                                        
                                        // Restore pagination state
                                        if (window.paginationConfig) {
                                            updatePagination();
                                        }
                                        
                                        // Rebind events
                                        rebindTableEvents();
                                        
                                        // Final cleanup to ensure scrolling is enabled
                                        $('.modal-backdrop').remove();
                                        $('body').removeClass('modal-open').css('padding-right', '');
                                    });
                                } else {
                                    Toast.error(response.message || 'Failed to remove user');
                                }
                            },
                            error: function(xhr, status, error) {
                                Toast.error('An error occurred while removing the user');
                                console.error("Error:", error);
                            },
                            complete: function() {
                                // Final cleanup in the complete handler
                                $('.modal-backdrop').remove();
                                $('body').removeClass('modal-open').css('padding-right', '');
                                
                                // Remove specific z-index backdrop
                                $('div.modal-backdrop[style*="z-index: 1040"]').remove();
                                
                                // Force enable scrolling
                                $('html, body').css({
                                    'overflow': '',
                                    'height': ''
                                });
                            }
                        });
                    }, 300); // Wait for modal hide animation to complete
                });
                
                // Show the confirmation modal
                $('#confirmDeleteModal').modal('show');
            });
            
            // Rebind sort headers
            $('.sort-header').off('click').on('click', function(e) {
                e.preventDefault();
                const column = $(this).data('sort');
                sortTable(column);
                filterTable(); // Apply filtering after sorting
            });
            
            // Rebind select-all checkbox
            $('#select-all').off('click').on('click', function() {
                $('.select-row').prop('checked', $(this).prop('checked'));
                updateBulkDeleteButtonVisibility();
            });
            
            // Rebind individual checkboxes
            $('.select-row').off('click').on('click', function() {
                updateBulkDeleteButtonVisibility();
            });
        }
        
        // Function to update bulk delete button visibility
        function updateBulkDeleteButtonVisibility() {
            const checkedCount = $('.select-row:checked').length;
            if (checkedCount >= 2) {
                $('#delete-selected').show().prop('disabled', false);
            } else {
                $('#delete-selected').hide().prop('disabled', true);
            }
        }

        // ... existing code ...

        // Update submitCreateUser handler
        $('#submitCreateUser').off('click').on('click', function() {
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
                        // Refresh only the table
                        refreshUserTable(function() {
                            Toast.success('User created successfully');
                            // Clear form
                            form[0].reset();
                            selectedDepartments = [];
                            $('#modal_department').val(null).trigger('change');
                            $('#createAssignedDepartmentsTable tbody').empty();
                            // Reset password UI
                            $('.password-strength').addClass('d-none');
                            $('.progress-bar').css('width', '0%').attr('aria-valuenow', '0');

                            // Hide modal
                            $('#createUserModal').modal('hide');
                        });
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
                            Toast.error(result.message || 'Failed to create user');
                        } else {
                            const result = JSON.parse(xhr.responseText);
                            Toast.error(result.message || 'Failed to create user');
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

        // Add or update edit user submission handler
        $('#submitEditUser').off('click').on('click', function() {
            const form = $('#editUserForm');
            const formData = new FormData(form[0]);
            
            // Validate email
            const email = $('#editEmail').val();
            if (!validateEmail(email)) {
                $('#editEmail').addClass('is-invalid');
                return;
            }
            
            // Check if departments have been added
            if (selectedDepartments.length === 0) {
                Toast.error('At least one department must be assigned');
                return;
            }
            
            // Clear existing department values
            formData.delete('departments[]');
            
            // Add all departments as array
            selectedDepartments.forEach((dept, index) => {
                formData.append(`departments[${index}]`, dept.id);
            });
            
            $.ajax({
                url: form.attr('action'),
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    const result = (typeof response === 'string') ? JSON.parse(response) : response;
                    if (result.success) {
                        // Refresh only the table
                        refreshUserTable(function() {
                            Toast.success('User updated successfully');
                            // Hide modal
                            $('#editUserModal').modal('hide');
                        });
                    } else {
                        Toast.error(result.message || 'Failed to update user');
                    }
                },
                error: function(xhr, status, error) {
                    try {
                        const result = JSON.parse(xhr.responseText);
                        Toast.error(result.message || 'Failed to update user');
                    } catch (e) {
                        Toast.error('Server error occurred. Please try again.');
                        console.error('Parse error:', e);
                    }
                }
            });
        });

        // Add bulk delete functionality
        $('#delete-selected').off('click').on('click', function() {
            const selectedIds = $('.select-row:checked').map(function() {
                return $(this).val();
            }).get();
            
            if (selectedIds.length === 0) {
                Toast.warning('No users selected for removal');
                return;
            }
            
            // Set up confirmation modal
            $('#confirmDeleteMessage').text(`Are you sure you want to remove ${selectedIds.length} selected users?`);
            
            // Set up confirm button action
            $('#confirmDeleteButton').off('click').on('click', function() {
                $.ajax({
                    url: 'bulk_delete_users.php',
                    type: 'POST',
                    data: {
                        user_ids: selectedIds
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Toast.success(`${selectedIds.length} users removed successfully`);
                            // Refresh table
                            refreshUserTable();
                            // Hide delete selected button
                            $('#delete-selected').hide().prop('disabled', true);
                            
                            // Force remove any lingering backdrop
                            $('.modal-backdrop').remove();
                            $('body').removeClass('modal-open').css('padding-right', '');
                        } else {
                            Toast.error(response.message || 'Failed to remove users');
                        }
                    },
                    error: function(xhr, status, error) {
                        Toast.error('An error occurred while removing users');
                        console.error("Error:", error);
                    }
                });
                
                // Hide confirmation modal
                $('#confirmDeleteModal').modal('hide');
            });
            
            // Show confirmation modal
            const deleteModal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
            deleteModal.show();
        });
        
        // Initial binding of events
        rebindTableEvents();
        
        // Handle select-all checkbox
        $('#select-all').off('click').on('click', function() {
            $('.select-row').prop('checked', $(this).prop('checked'));
            updateBulkDeleteButtonVisibility();
        });
        
        // Handle individual row selection
        $(document).on('click', '.select-row', function() {
            updateBulkDeleteButtonVisibility();
        });
        
        // ... existing code ...

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
        });

        // Initialize selectedDepartments array for the global scope
        let selectedDepartments = [];

        // Email validation function
        function validateEmail(email) {
            const regex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
            return regex.test(email);
        }

        // Handle delete button click
        $(document).on('click', '.delete-btn', function() {
            const userId = $(this).data('id');
            const username = $(this).closest('tr').find('td:eq(1)').text(); // Get username from the second column
            
            // Close any open Select2 dropdowns first
            if ($.fn.select2) {
                $('select.select2-hidden-accessible').select2('close');
            }
            
            // Ensure any existing backdrops are removed first
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open').css('padding-right', '');
            
            // Set up the confirmation modal
            $('#confirmDeleteMessage').text(`Are you sure you want to remove user "${username}"?`);
            
            // Set up the confirm button action
            $('#confirmDeleteButton').off('click').on('click', function() {
                // First hide the modal completely and clean up
                $('#confirmDeleteModal').modal('hide');
                
                // Wait for the modal to finish hiding before continuing
                setTimeout(function() {
                    // Clean up modal artifacts
                    $('.modal-backdrop').remove();
                    $('body').removeClass('modal-open').css('padding-right', '');
                    
                    // Remove backdrop with specific z-index
                    $('div.modal-backdrop[style*="z-index: 1040"]').remove();
                    
                // Send delete request
                $.ajax({
                    url: 'delete_user.php',
                    type: 'POST',
                    data: {
                        user_id: userId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Toast.success('User removed successfully');
                                
                                // Clean up again before refreshing table
                                $('.modal-backdrop').remove();
                                $('body').removeClass('modal-open').css('padding-right', '');
                                
                                // Manually reload the table data
                                $.get(location.href, function(data) {
                                    // Extract only the table HTML from the response
                                    const tableHtml = $(data).find('#umTable').html();
                                    $('#umTable').html(tableHtml);
                                    
                                    // Re-initialize pagination and events
                                    window.allRows = Array.from(document.querySelectorAll('#umTableBody tr'));
                                    window.filteredRows = window.allRows;
                                    
                                    // Restore pagination state
                                    if (window.paginationConfig) {
                                        updatePagination();
                                    }
                                    
                                    // Rebind events
                                    rebindTableEvents();
                                    
                                    // Final cleanup to ensure scrolling is enabled
                                    $('.modal-backdrop').remove();
                                    $('body').removeClass('modal-open').css('padding-right', '');
                                });
                        } else {
                            Toast.error(response.message || 'Failed to remove user');
                        }
                    },
                    error: function(xhr, status, error) {
                        Toast.error('An error occurred while removing the user');
                        console.error("Error:", error);
                        },
                        complete: function() {
                            // Final cleanup in the complete handler
                            $('.modal-backdrop').remove();
                            $('body').removeClass('modal-open').css('padding-right', '');
                            
                            // Remove specific z-index backdrop
                            $('div.modal-backdrop[style*="z-index: 1040"]').remove();
                            
                            // Force enable scrolling
                            $('html, body').css({
                                'overflow': '',
                                'height': ''
                            });
                        }
                    });
                }, 300); // Wait for modal hide animation to complete
            });
            
            // Show the confirmation modal with static backdrop option
            $('#confirmDeleteModal').modal({
                backdrop: 'static',
                keyboard: false
            }).modal('show');
        });

        // Add debounce function to prevent too many searches
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        // Live search functionality
        const searchInput = document.getElementById('search-filters');
        const searchHandler = debounce(function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('#umTableBody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const matches = text.includes(searchTerm);
                row.style.display = matches ? '' : 'none';
            });

            // Update pagination after search
            if (typeof window.updatePagination === 'function') {
                window.filteredRows = Array.from(document.querySelectorAll('#umTableBody tr:not([style*="display: none"])'));
                window.paginationConfig.currentPage = 1; // Reset to first page
                window.updatePagination();
                window.forcePaginationCheck();
            }
        }, 300); // 300ms delay

        // Add event listener for live search
        searchInput.addEventListener('input', searchHandler);

        // Preserve search value on form submit
        const filterForm = document.getElementById('userFilterForm');
        filterForm.addEventListener('submit', function(e) {
            const searchValue = searchInput.value;
            if (searchValue) {
                const searchInput = document.createElement('input');
                searchInput.type = 'hidden';
                searchInput.name = 'search';
                searchInput.value = searchValue;
                filterForm.appendChild(searchInput);
            }
        });
    </script>

    <!-- Add Toast notification utility and date filter handling -->
    <script>
        // Toast notification utility
        const Toast = {
            container: document.createElement('div'),
            
            init: function() {
                // Create container if it doesn't exist
                if (!document.querySelector('.toast-container')) {
                    const container = document.createElement('div');
                    container.className = 'toast-container position-fixed top-0 end-0 p-3';
                    document.body.appendChild(container);
                }
                this.container = document.querySelector('.toast-container');
            },
            
            create: function(message, type) {
                this.init();
                
                // Create toast element
                const toastEl = document.createElement('div');
                toastEl.className = `toast align-items-center text-white bg-${type} border-0`;
                toastEl.setAttribute('role', 'alert');
                toastEl.setAttribute('aria-live', 'assertive');
                toastEl.setAttribute('aria-atomic', 'true');
                
                // Create toast content
                toastEl.innerHTML = `
                    <div class="d-flex">
                        <div class="toast-body">
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                `;
                
                // Add to container
                this.container.appendChild(toastEl);
                
                // Initialize and show toast
                const toast = new bootstrap.Toast(toastEl, {
                    autohide: true,
                    delay: 5000
                });
                toast.show();
                
                // Remove from DOM after hidden
                toastEl.addEventListener('hidden.bs.toast', function() {
                    toastEl.remove();
                });
            },
            
            success: function(message) {
                this.create(message, 'success');
            },
            
            error: function(message) {
                this.create(message, 'danger');
            },
            
            warning: function(message) {
                this.create(message, 'warning');
            },
            
            info: function(message) {
                this.create(message, 'info');
            }
        };

        document.addEventListener('DOMContentLoaded', function() {
            // Date filter handling
            const filterType = document.getElementById('dateFilterType');
            const allDateFilters = document.querySelectorAll('.date-filter');
            const form = document.getElementById('userFilterForm');
            const clearButton = document.getElementById('clear-filters-btn');
            const dateInputsContainer = document.getElementById('dateInputsContainer');

            function updateDateFields() {
                allDateFilters.forEach(field => field.classList.add('d-none'));
                if (!filterType.value) {
                    dateInputsContainer.classList.add('d-none');
                    return;
                }
                dateInputsContainer.classList.remove('d-none');
                document.querySelectorAll('.date-' + filterType.value).forEach(field => field.classList.remove('d-none'));
            }

            filterType.addEventListener('change', updateDateFields);
            updateDateFields();

            clearButton.addEventListener('click', function(e) {
                e.preventDefault(); // Prevent default button behavior
                
                // Reset all form fields
                form.reset();
                
                // Clear any hidden fields
                const hiddenFields = form.querySelectorAll('input[type="hidden"]');
                hiddenFields.forEach(field => field.value = '');
                
                // Reset date filter visibility
                updateDateFields();
                
                // Clear URL parameters and reload the page
                window.location.href = window.location.pathname;
            });

            // Initialize Select2 for date filter type dropdown if needed
            if ($.fn.select2 && $('#dateFilterType').length) {
                $('#dateFilterType').select2({
                    placeholder: '-- Select Type --',
                    allowClear: true,
                    width: '100%',
                    minimumResultsForSearch: -1 // Hide search box
                }).on('select2:select select2:clear', function() {
                    // Force trigger the change event
                    setTimeout(() => {
                        $(this).trigger('change');
                    }, 10);
                });
            }

            // Add sorting functionality
            const sortableHeaders = document.querySelectorAll('.sortable');
            sortableHeaders.forEach(header => {
                header.addEventListener('click', function() {
                    const column = this.dataset.column;
                    const currentOrder = '<?= $sortOrder ?>';
                    const currentColumn = '<?= $sortColumn ?>';
                    
                    // Determine new sort order
                    let newOrder = 'ASC';
                    if (column === currentColumn) {
                        newOrder = currentOrder === 'ASC' ? 'DESC' : 'ASC';
                    }
                    
                    // Get current URL parameters
                    const urlParams = new URLSearchParams(window.location.search);
                    
                    // Update sort parameters
                    urlParams.set('sort', column);
                    urlParams.set('order', newOrder);
                    
                    // Redirect to new URL with sort parameters
                    window.location.href = window.location.pathname + '?' + urlParams.toString();
                });
            });
        });
    </script>

    <!-- Direct date filter handler - This will ensure the date filter UI works immediately -->
    <script>
        $(function() {
            // Initialize Select2 for date filter type dropdown if not already initialized
            if ($.fn.select2 && $('#dateFilterType').length && !$('#dateFilterType').hasClass('select2-hidden-accessible')) {
                $('#dateFilterType').select2({
                    placeholder: '-- Select Type --',
                    allowClear: true,
                    width: '100%',
                    minimumResultsForSearch: -1 // Hide search box
                }).on('select2:select select2:clear', function() {
                    // Force trigger the change event
                    setTimeout(() => {
                        $(this).trigger('change');
                    }, 10);
                });
            }
            
            // Direct handler for date filter type changes
            $('#dateFilterType').off('change').on('change', function() {
                const selectedType = $(this).val();
                console.log('DIRECT HANDLER: Date filter type changed to:', selectedType);
                
                // Hide all date filter containers first
                $('.date-filter').addClass('d-none');
                
                // Hide the container if no filter type selected
                if (!selectedType) {
                    $('#dateInputsContainer').addClass('d-none');
                    return;
                }
                
                // Show the container and the specific filter type fields
                $('#dateInputsContainer').removeClass('d-none');
                $('.date-' + selectedType).removeClass('d-none');
            });
            
            // Trigger the change event on page load if a value is already selected
            if ($('#dateFilterType').val()) {
                $('#dateFilterType').trigger('change');
            }
            
            // Also handle the clear filters button
            $('#clear-filters-btn').on('click', function() {
                // Reset the date filter type dropdown
                if ($('#dateFilterType').hasClass('select2-hidden-accessible')) {
                    $('#dateFilterType').val('').trigger('change.select2').trigger('change');
                } else {
                    $('#dateFilterType').val('').trigger('change');
                }
                
                // Hide all date filter containers
                $('#dateInputsContainer').addClass('d-none');
                $('.date-filter').addClass('d-none');
                $('#filterError').remove();
            });
        });
    </script>
</body>

</html>