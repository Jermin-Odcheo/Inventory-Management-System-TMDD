<?php
/**
 * @file manage_roles.php
 * @brief handles the management of roles within the system
 *
 * This script handles the management of roles within the system, including viewing, creating, modifying,
 * and deleting roles. It implements Role-Based Access Control (RBAC) to enforce user privileges,
 * provides filtering and pagination for role data, and manages the display of roles and their associated
 * modules and privileges.
 */
ob_start();
require_once('../../../../../../config/ims-tmdd.php');
session_start();
include '../../../general/header.php';
include '../../../general/sidebar.php';
include '../../../general/footer.php';
echo '<script>document.body.classList.add("manage-roles");</script>';

/**
 * Authentication Guard
 *
 * Ensures that the user is authenticated by checking for a valid user ID in the session.
 * Redirects to the login page if the user is not authenticated.
 */
$userId = $_SESSION['user_id'] ?? null;
if (!is_int($userId) && !ctype_digit((string)$userId)) {
    header("Location: " . BASE_URL . "index.php");
    exit();
}
$userId = (int)$userId;

/**
 * Initialize RBAC and Enforce View Privilege
 *
 * Sets up Role-Based Access Control (RBAC) for the current user and ensures they have the necessary
 * 'View' privilege to access role management functionality.
 */
$rbac = new RBACService($pdo, $_SESSION['user_id']);
$rbac->requirePrivilege('Roles and Privileges', 'View');

/**
 * Set Button Privilege Flags
 *
 * Determines the user's privileges for various actions (Create, Modify, Remove, Undo, Redo, View Archive)
 * to control the visibility and functionality of UI elements.
 */
$canCreate = $rbac->hasPrivilege('Roles and Privileges', 'Create');
$canModify = $rbac->hasPrivilege('Roles and Privileges', 'Modify');
$canRemove = $rbac->hasPrivilege('Roles and Privileges', 'Remove');
$canUndo = $rbac->hasPrivilege('Roles and Privileges', 'Undo');
$canRedo = $rbac->hasPrivilege('Roles and Privileges', 'Redo');
$canViewArchive = $rbac->hasPrivilege('Roles and Privileges', 'View');

/**
 * Fetch Modules for Filter Dropdown
 *
 * Retrieves a list of distinct modules from the database to populate the module filter dropdown.
 */
$moduleQuery = "SELECT DISTINCT id, module_name FROM modules ORDER BY module_name";
$moduleStmt = $pdo->prepare($moduleQuery);
$moduleStmt->execute();
$moduleOptions = $moduleStmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * Fetch Privileges for Filter Dropdown
 *
 * Retrieves a list of distinct, non-disabled privileges from the database to populate the privilege filter dropdown.
 */
$privilegeQuery = "SELECT DISTINCT id, priv_name FROM privileges WHERE is_disabled = 0 ORDER BY priv_name";
$privilegeStmt = $pdo->prepare($privilegeQuery);
$privilegeStmt->execute();
$privilegeOptions = $privilegeStmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * Check if Filter is Applied
 *
 * Determines whether a filter has been applied based on the presence of a specific GET parameter.
 */
$isFiltered = isset($_GET['filter_applied']) && $_GET['filter_applied'] === '1';

/**
 * Pagination Setup
 *
 * Sets up pagination parameters including the current page, rows per page, and calculates the offset
 * for database queries to display the correct set of data.
 */
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$rowsPerPage = isset($_GET['rows_per_page']) ? max(5, intval($_GET['rows_per_page'])) : 10;
$offset = ($currentPage - 1) * $rowsPerPage;

/**
 * Count Total Roles with Filters
 *
 * Calculates the total number of distinct roles that match the applied filters to determine pagination.
 */
$countSql = "
SELECT COUNT(DISTINCT r.id) as total_count
FROM roles r
WHERE r.is_disabled = 0
";

/**
 * Build Main SQL Query for Role Data
 *
 * Constructs the main SQL query to fetch role data along with associated modules and privileges.
 */
$sql = "
SELECT 
    r.id AS Role_ID,
    r.role_name AS Role_Name,
    m.id AS Module_ID,
    m.module_name AS Module_Name,
    COALESCE((
        SELECT GROUP_CONCAT(p.priv_name ORDER BY p.priv_name SEPARATOR ', ')
        FROM role_module_privileges rmp2
        JOIN privileges p ON p.id = rmp2.privilege_id
        WHERE rmp2.role_id = r.id
          AND rmp2.module_id = m.id
    ), 'No privileges') AS Privileges
FROM roles r
CROSS JOIN modules m
WHERE r.is_disabled = 0
";

/**
 * Apply Filters to Query
 *
 * Applies filters to the SQL query based on module, privilege, and search criteria provided by the user.
 */
$params = [];
$whereConditions = [];
$havingConditions = [];
$filteredRoleIds = [];

if ($isFiltered) {
    // Module filter
    if (!empty($_GET['module'])) {
        $moduleFilter = $_GET['module'];
        
        // Get roles that have privileges for the specified module
        $moduleFilterQuery = "
            SELECT DISTINCT rmp.role_id
            FROM role_module_privileges rmp
            JOIN modules m ON rmp.module_id = m.id
            WHERE m.module_name = :module_name
        ";
        $moduleFilterStmt = $pdo->prepare($moduleFilterQuery);
        $moduleFilterStmt->execute([':module_name' => $moduleFilter]);
        $filteredRoleIds = $moduleFilterStmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($filteredRoleIds)) {
            $whereConditions[] = "r.id IN (" . implode(',', $filteredRoleIds) . ")";
        } else {
            // No roles have this module, return empty result
            $whereConditions[] = "1=0";
        }
    }

    // Privilege filter
    if (!empty($_GET['privilege']) && is_array($_GET['privilege'])) {
        $privilegeFilters = array_filter($_GET['privilege']); // Remove empty values
        
        if (!empty($privilegeFilters)) {
            // Initialize array to store roles that match ALL of the selected privileges
            $firstPrivilege = true;
            $privFilteredRoleIds = [];
            
            foreach ($privilegeFilters as $privilegeFilter) {
        // Get roles that have the specified privilege
        $privFilterQuery = "
            SELECT DISTINCT rmp.role_id
            FROM role_module_privileges rmp
            JOIN privileges p ON rmp.privilege_id = p.id
            WHERE p.priv_name = :priv_name
        ";
        $privFilterStmt = $pdo->prepare($privFilterQuery);
        $privFilterStmt->execute([':priv_name' => $privilegeFilter]);
                $currentPrivFilteredRoleIds = $privFilterStmt->fetchAll(PDO::FETCH_COLUMN);
                
                // For the first privilege, set the initial role IDs
                if ($firstPrivilege) {
                    $privFilteredRoleIds = $currentPrivFilteredRoleIds;
                    $firstPrivilege = false;
                } else {
                    // For subsequent privileges, keep only roles that have ALL privileges (intersection)
                    $privFilteredRoleIds = array_intersect($privFilteredRoleIds, $currentPrivFilteredRoleIds);
                }
                
                // If at any point we have no matching roles, break early
                if (empty($privFilteredRoleIds)) {
                    break;
                }
            }
        
        if (!empty($privFilteredRoleIds)) {
            // If we already have module filter results, intersect with privilege filter results
            if (!empty($filteredRoleIds)) {
                $filteredRoleIds = array_intersect($filteredRoleIds, $privFilteredRoleIds);
                if (empty($filteredRoleIds)) {
                    // No intersection, return empty result
                    $whereConditions[] = "1=0";
                } else {
                    $whereConditions[] = "r.id IN (" . implode(',', $filteredRoleIds) . ")";
                }
            } else {
                $whereConditions[] = "r.id IN (" . implode(',', $privFilteredRoleIds) . ")";
            }
        } else {
                // No roles have ALL of these privileges, return empty result
            $whereConditions[] = "1=0";
            }
        }
    }

    // Enhanced search filter (applies to role name, module name, and privileges)
    if (!empty($_GET['search'])) {
        $searchTerm = '%' . $_GET['search'] . '%';
        
        // Create a comprehensive search condition
        $searchConditions = [];
        
        // Search in role names
        $searchConditions[] = "r.role_name LIKE :search_role";
        $params[':search_role'] = $searchTerm;
        
        // Search in module names
        $searchConditions[] = "EXISTS (
            SELECT 1 FROM modules m2 
            WHERE m2.module_name LIKE :search_module 
            AND EXISTS (
                SELECT 1 FROM role_module_privileges rmp2 
                WHERE rmp2.role_id = r.id AND rmp2.module_id = m2.id
            )
        )";
        $params[':search_module'] = $searchTerm;
        
        // Search in privileges
        $searchConditions[] = "EXISTS (
            SELECT 1 FROM role_module_privileges rmp3
            JOIN privileges p ON rmp3.privilege_id = p.id
            WHERE rmp3.role_id = r.id AND p.priv_name LIKE :search_priv
        )";
        $params[':search_priv'] = $searchTerm;
        
        // Combine all search conditions with OR
        $whereConditions[] = "(" . implode(" OR ", $searchConditions) . ")";
    }
}

/**
 * Add Conditions to SQL Queries
 *
 * Adds the constructed WHERE conditions to both the main SQL query and the count query.
 */
if (!empty($whereConditions)) {
    $whereClause = " AND " . implode(" AND ", $whereConditions);
    $sql .= $whereClause;
    $countSql .= $whereClause;
}

/**
 * Execute Count Query
 *
 * Executes the count query to determine the total number of roles matching the filters for pagination.
 */
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalCount = $countStmt->fetchColumn();

/**
 * Calculate Pagination
 *
 * Calculates the total number of pages based on the total count of roles and rows per page.
 * Adjusts the current page if it exceeds the total pages.
 */
$totalPages = ceil($totalCount / $rowsPerPage);
if ($totalPages > 0 && $currentPage > $totalPages) {
    $currentPage = $totalPages;
    $offset = ($currentPage - 1) * $rowsPerPage;
}

/**
 * Fetch Role IDs for Current Page
 *
 * Retrieves the distinct role IDs for the current page based on the pagination offset and limit.
 */
$roleIdSql = "
SELECT DISTINCT r.id
FROM roles r
WHERE r.is_disabled = 0
";

if (!empty($whereConditions)) {
    $roleIdSql .= " AND " . implode(" AND ", $whereConditions);
}

$roleIdSql .= " ORDER BY r.id DESC LIMIT :limit OFFSET :offset";

$roleIdStmt = $pdo->prepare($roleIdSql);
$roleIdStmt->bindParam(':limit', $rowsPerPage, PDO::PARAM_INT);
$roleIdStmt->bindParam(':offset', $offset, PDO::PARAM_INT);
foreach ($params as $key => $value) {
    $roleIdStmt->bindValue($key, $value);
}
$roleIdStmt->execute();
$pageRoleIds = $roleIdStmt->fetchAll(PDO::FETCH_COLUMN);

/**
 * Refine Main Query with Role IDs
 *
 * Refines the main SQL query to include only the role IDs for the current page.
 */
if (!empty($pageRoleIds)) {
    $sql .= " AND r.id IN (" . implode(',', $pageRoleIds) . ")";
}

$sql .= " ORDER BY r.id DESC, m.id";

/**
 * Execute Main Query and Fetch Role Data
 *
 * Executes the main SQL query to fetch detailed role data for display.
 */
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$roleData = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * Group Role Data
 *
 * Groups the fetched data by role and module, ensuring unique privileges per module for display.
 */
$roles = [];
foreach ($roleData as $row) {
    $roleID = $row['Role_ID'];
    $moduleID = $row['Module_ID'];
    $moduleName = !empty($row['Module_Name']) ? $row['Module_Name'] : 'General';
    
    if (!isset($roles[$roleID])) {
        $roles[$roleID] = [
            'Role_Name' => $row['Role_Name'],
            'Modules' => []
        ];
    }
    
    // Ensure each module is stored separately
    if (!isset($roles[$roleID]['Modules'][$moduleName])) {
        $roles[$roleID]['Modules'][$moduleName] = [];
    }
    
    // Only add privileges if they exist
    if ($row['Privileges'] !== 'No privileges') {
        // Split the privileges string and add each privilege individually
        foreach (explode(', ', $row['Privileges']) as $privilege) {
            if (!empty($privilege) && !in_array($privilege, $roles[$roleID]['Modules'][$moduleName])) {
                $roles[$roleID]['Modules'][$moduleName][] = $privilege;
            }
        }
    }
}

// Ensure unique privileges per module
foreach ($roles as $roleID => &$role) {
    foreach ($role['Modules'] as &$privileges) {
        $privileges = array_values(array_unique($privileges));
    }
}
unset($role, $privileges);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Role Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        .wrapper {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            transition: margin-left 0.3s ease;
        }

        .main-content.sidebar-collapsed {
            margin-left: 60px;
        }

        #tableContainer {
            max-height: 500px;
            overflow-y: auto;
        }

        /* Button Styles */
        .edit-btn,
        .delete-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.375rem;
            border-radius: 9999px;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2rem;
            height: 2rem;
        }

        .edit-btn {
            color: #4f46e5;
        }

        .delete-btn {
            color: #ef4444;
        }

        .edit-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .delete-btn:hover {
            background-color: #fee2e2;
        }

        .edit-btn:active {
            transform: translateY(0);
        }

        /* Modal backdrop fix */
        .modal-backdrop {
            opacity: 0.5 !important; /* Force consistent opacity with !important */
            background-color: #000;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1040;
            width: 100vw;
            height: 100vh;
            transition: none !important; /* Disable any transitions */
            animation: none !important; /* Disable any animations */
        }
        
        /* Disable fade transitions for modals and backdrops */
        .modal-backdrop.fade,
        .modal.fade,
        .modal.fade .modal-dialog {
            transition: none !important;
            animation: none !important;
        }
        
        /* Ensure consistent backdrop appearance in all states */
        .modal-backdrop.show {
            opacity: 0.5 !important;
        }
        
        body.modal-open {
            overflow: hidden;
            padding-right: 0 !important;
        }
        
        /* Ensure modals are on top of backdrop */
        .modal {
            z-index: 1050;
        }
        
        /* Select2 Multiple Select Styling */
        .select2-container--default .select2-selection--multiple {
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
            min-height: 38px;
            padding: 2px 5px;
        }
        
        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background-color: #212529;
            color: #fff;
            border: none;
            border-radius: 4px;
            padding: 2px 8px;
            margin: 3px 5px 3px 0;
            display: flex;
            align-items: center;
        }
        
        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
            color: #fff;
            margin-right: 5px;
            font-size: 16px;
            /* line-height: 1; */
            border: none;
            background: transparent;
            padding: 0 4px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
            color: #f8f9fa;
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        .select2-dropdown {
            border-color: #ced4da;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: #212529;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice__display{
            padding-left: 10px;
        }
        /* Fix for select2 search box */
        .select2-search--dropdown .select2-search__field {
            padding: 6px 8px;
            border-radius: 4px;
            border: 1px solid #ced4da;
        }
        
        /* Fix for select2 dropdown padding */
        .select2-results__options {
            padding: 6px;
        }
        
        /* Improve select2 placeholder */
        .select2-container--default .select2-selection--multiple .select2-selection__placeholder {
            color: #6c757d;
            padding: 0 5px;
        }
    </style>
</head>

<body>

    <div class="wrapper">
        <div class="main-content container-fluid">
            <div class="row">
                <main class="col-md-12 px-md-4 py-4">
                    <h2 class="mb-4">Role Management</h2>

                    <div class="card shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center bg-dark text-white">
                            <span><i class="bi bi-list-ul"></i> List of Roles</span>
                        </div>
                        <div class="card-body">
                            <!-- Always show the filter container -->
                            <div class="filter-container">
                                <div class="d-flex justify-content-start mb-3 gap-2 align-items-center">
                                    <?php if ($canCreate): ?>
                                        <button type="button" class="btn btn-success btn-dark" data-bs-toggle="modal"
                                            data-bs-target="#addRoleModal">
                                            <i class="bi bi-plus-lg"></i> Create New Role
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Filter Section - Always visible -->
                            <form method="GET" class="row g-3 mb-4" id="roleFilterForm">
                                <input type="hidden" name="filter_applied" value="1">
                                <!-- Add hidden page parameter to reset to page 1 when filtering -->
                                <input type="hidden" name="page" value="1">
                                <!-- Add hidden rows per page parameter to maintain the setting -->
                                <input type="hidden" name="rows_per_page" value="<?= $rowsPerPage ?>">
                                
                                <div class="col-md-4">
                                    <label for="moduleFilter" class="form-label">Module</label>
                                    <select class="form-select" name="module" id="moduleFilter">
                                        <option value="">All Modules</option>
                                        <?php foreach ($moduleOptions as $module): ?>
                                            <option value="<?= htmlspecialchars($module['module_name']) ?>" 
                                                <?= ($_GET['module'] ?? '') === $module['module_name'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($module['module_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-4">
                                    <label for="privilegeFilter" class="form-label">Privilege</label>
                                    <select class="form-select" name="privilege[]" id="privilegeFilter" multiple>
                                        <?php foreach ($privilegeOptions as $privilege): ?>
                                            <?php 
                                            $selectedPrivs = isset($_GET['privilege']) ? (is_array($_GET['privilege']) ? $_GET['privilege'] : [$_GET['privilege']]) : [];
                                            $isSelected = in_array($privilege['priv_name'], $selectedPrivs) ? 'selected' : '';
                                            ?>
                                            <option value="<?= htmlspecialchars($privilege['priv_name']) ?>" <?= $isSelected ?>>
                                                <?= htmlspecialchars($privilege['priv_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Select multiple privileges to find roles with ALL selected privileges. Click to search.</div>
                                </div>
                                
                                <!-- Search bar - now with expanded width -->
                                <div class="col-12 col-sm-6 col-md-4">
                                    <label class="form-label">Search</label>
                                    <div class="input-group shadow-sm">
                                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                                        <input type="text" name="search" id="searchInput" class="form-control" 
                                               placeholder="Search roles, modules, or privileges..." 
                                               value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                                    </div>
                                </div>

                                <div class="col-6 col-md-2 d-grid">
                                    <button type="submit" id="applyFilters" class="btn btn-dark">
                                        <i class="bi bi-funnel"></i> Filter
                                    </button>
                                </div>

                                <div class="col-6 col-md-2 d-grid">
                                    <button type="button" id="clearFilters" class="btn btn-secondary shadow-sm">
                                        <i class="bi bi-x-circle"></i> Clear
                                    </button>
                                </div>
                            </form>

                            <!-- Table - Show appropriate message if no roles found -->
                            <div class="table-responsive" id="table">
                                <table id="rolesTable" class="table table-striped table-hover align-middle">
                                    <thead class="table-dark">
                                        <tr>
                                            <th style="width: 25px;">ID</th>
                                            <th style="width: 250px;">Role Name</th>
                                            <th>Modules & Privileges</th>
                                            <th style="width: 250px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="auditTable">
                                        <?php if (!empty($roles)): ?>
                                            <?php foreach ($roles as $roleID => $role): ?>
                                                <tr data-role-id="<?php echo $roleID; ?>">
                                                    <td><?php echo htmlspecialchars($roleID); ?></td>
                                                    <td class="role-name"><?php echo htmlspecialchars($role['Role_Name']); ?></td>
                                                    <td class="privilege-list">
                                                        <?php foreach ($role['Modules'] as $moduleName => $privileges): ?>
                                                            <div>
                                                                <strong><?php echo htmlspecialchars($moduleName); ?></strong>:
                                                                <?php echo !empty($privileges)
                                                                    ? implode(', ', array_map('htmlspecialchars', $privileges))
                                                                    : '<em>No privileges</em>'; ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($canModify): ?>
                                                            <button type="button" class="edit-btn edit-role-btn"
                                                                data-role-id="<?php echo $roleID; ?>" data-bs-toggle="modal"
                                                                data-bs-target="#editRoleModal">
                                                                <i class="bi bi-pencil-square"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        <?php if ($canRemove): ?>
                                                            <button type="button" class="delete-btn delete-role-btn"
                                                                data-role-id="<?php echo $roleID; ?>"
                                                                data-role-name="<?php echo htmlspecialchars($role['Role_Name']); ?>"
                                                                data-bs-toggle="modal" data-bs-target="#confirmDeleteModal">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="4" class="text-center py-3">
                                                    <div class="alert alert-info mb-0">
                                                        <?php if ($isFiltered): ?>
                                                            <i class="bi bi-info-circle me-2"></i>No roles found matching your filter criteria. 
                                                            <button type="button" class="btn btn-link p-0 align-baseline" id="inlineResetFilters">Clear filters</button>
                                                        <?php else: ?>
                                                            <i class="bi bi-info-circle me-2"></i>No roles found in the system.
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>

                                <!-- Pagination -->
                                <?php if (!empty($roles)): ?>
                                <div class="container-fluid">
                                    <div class="row align-items-center g-3">
                                        <div class="col-12 col-sm-auto">
                                            <div class="text-muted">
                                                <?php 
                                                $start = min(($currentPage - 1) * $rowsPerPage + 1, $totalCount);
                                                $end = min($currentPage * $rowsPerPage, $totalCount);
                                                ?>
                                                Showing <span id="currentPage"><?= $start ?></span> to <span id="rowsPerPage"><?= $end ?></span> of <span
                                                    id="totalRows"><?= $totalCount ?></span> entries
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-auto ms-sm-auto">
                                            <div class="d-flex align-items-center gap-2">
                                                <?php 
                                                $prevPageUrl = http_build_query(array_merge($_GET, ['page' => max(1, $currentPage - 1)]));
                                                $nextPageUrl = http_build_query(array_merge($_GET, ['page' => $currentPage + 1]));
                                                $prevDisabled = $currentPage <= 1 ? 'disabled' : '';
                                                $nextDisabled = $currentPage >= $totalPages ? 'disabled' : '';
                                                ?>
                                                <a href="?<?= $prevPageUrl ?>" id="prevPage" class="btn btn-outline-primary d-flex align-items-center gap-1 <?= $prevDisabled ?>">
                                                    <i class="bi bi-chevron-left"></i> Previous
                                                </a>
                                                <select id="rowsPerPageSelect" class="form-select" style="width: auto;">
                                                    <option value="5" <?= $rowsPerPage == 5 ? 'selected' : '' ?>>5</option>
                                                    <option value="10" <?= $rowsPerPage == 10 ? 'selected' : '' ?>>10</option>
                                                    <option value="20" <?= $rowsPerPage == 20 ? 'selected' : '' ?>>20</option>
                                                    <option value="50" <?= $rowsPerPage == 50 ? 'selected' : '' ?>>50</option>
                                                </select>
                                                <a href="?<?= $nextPageUrl ?>" id="nextPage" class="btn btn-outline-primary d-flex align-items-center gap-1 <?= $nextDisabled ?>">
                                                    Next <i class="bi bi-chevron-right"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row mt-3">
                                        <div class="col-12">
                                            <ul class="pagination justify-content-center" id="pagination">
                                                <?php 
                                                // Display pagination with ellipsis for large number of pages
                                                $maxPagesToShow = 5;
                                                $startPage = max(1, $currentPage - floor($maxPagesToShow / 2));
                                                $endPage = min($totalPages, $startPage + $maxPagesToShow - 1);
                                                
                                                // Adjust start page if we're near the end
                                                if ($endPage - $startPage + 1 < $maxPagesToShow) {
                                                    $startPage = max(1, $endPage - $maxPagesToShow + 1);
                                                }
                                                
                                                // First page link
                                                if ($startPage > 1) {
                                                    $pageUrl = http_build_query(array_merge($_GET, ['page' => 1]));
                                                    echo '<li class="page-item"><a class="page-link" href="?' . $pageUrl . '">1</a></li>';
                                                    
                                                    if ($startPage > 2) {
                                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                                    }
                                                }
                                                
                                                // Page links
                                                for ($i = $startPage; $i <= $endPage; $i++) {
                                                    $pageUrl = http_build_query(array_merge($_GET, ['page' => $i]));
                                                    $activeClass = $i == $currentPage ? 'active' : '';
                                                    echo '<li class="page-item ' . $activeClass . '"><a class="page-link" href="?' . $pageUrl . '">' . $i . '</a></li>';
                                                }
                                                
                                                // Last page link
                                                if ($endPage < $totalPages) {
                                                    if ($endPage < $totalPages - 1) {
                                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                                    }
                                                    
                                                    $pageUrl = http_build_query(array_merge($_GET, ['page' => $totalPages]));
                                                    echo '<li class="page-item"><a class="page-link" href="?' . $pageUrl . '">' . $totalPages . '</a></li>';
                                                }
                                                ?>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </div>

    <!-- Removed pagination.js dependency since we're using server-side pagination -->

    <!-- Modals (unchanged) -->
    <div class="modal" id="editRoleModal" tabindex="-1" aria-labelledby="editRoleModalLabel" aria-hidden="true" style="transition: none !important;">
        <div class="modal-dialog modal-lg modal-dialog-centered" style="transition: none !important;">
            <div class="modal-content">
                <div id="editRoleContent">Loading...</div>
            </div>
        </div>
    </div>

    <div class="modal" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel"
        aria-hidden="true" style="transition: none !important;">
        <div class="modal-dialog modal-dialog-centered" style="transition: none !important;">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete the role "<span id="roleNamePlaceholder"></span>"?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a id="confirmDeleteButton" href="#" class="btn btn-danger">Delete</a>
                </div>
            </div>
        </div>
    </div>

    <div class="modal" id="addRoleModal" tabindex="-1" aria-labelledby="addRoleModalLabel" aria-hidden="true" style="transition: none !important;">
        <div class="modal-dialog modal-dialog-centered" style="transition: none !important;">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="addRoleContent">Loading...</div>
            </div>
        </div>
    </div>

    <script>
        // Pass RBAC privileges to JavaScript
        const userPrivileges = {
            canCreate: <?php echo json_encode($canCreate); ?>,
            canModify: <?php echo json_encode($canModify); ?>,
            canRemove: <?php echo json_encode($canRemove); ?>,
            canUndo: <?php echo json_encode($canUndo); ?>,
            canRedo: <?php echo json_encode($canRedo); ?>,
            canViewArchive: <?php echo json_encode($canViewArchive); ?>
        };

        // Function to refresh the roles table without page reload
        function refreshRolesTable() {
            // Store current scroll position
            const scrollPosition = window.scrollY || document.documentElement.scrollTop;

            // Don't hide modals or remove backdrops here - just refresh the table data
            $.ajax({
                url: location.href,
                type: 'GET',
                success: function(response) {
                    // Extract just the table HTML from the response
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(response, 'text/html');
                    const newTable = doc.querySelector('#rolesTable');

                    if (newTable) {
                        // Replace the current table with the new one
                        $('#rolesTable').replaceWith(newTable);

                        // Reset the global arrays for pagination
                        window.allRows = Array.from(document.querySelectorAll('#auditTable tr'));
                        window.filteredRows = window.allRows;
                        window.currentPage = 1;

                        // Reinitialize pagination
                        if (typeof updatePagination === 'function') {
                            updatePagination();
                            setTimeout(forcePaginationCheck, 100);
                        }

                        // Restore scroll position after everything is loaded
                        setTimeout(function() {
                            window.scrollTo(0, scrollPosition);
                        }, 150);
                    } else {
                        console.error('Could not find table in response');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error refreshing table:', error);
                    showToast('Failed to refresh data. Please reload the page.', 'error', 5000);
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Clear any previously defined pagination variables
            window.paginationConfig = null;
            
            // Initialize our own pagination variables
            window.allRows = Array.from(document.querySelectorAll('#auditTable tr'));
            window.filteredRows = window.allRows;
            window.currentPage = 1;
            
            console.log(`Initialized pagination with ${window.allRows.length} total rows`);
            
            // Basic error checks for required elements
            if (!document.getElementById('auditTable')) {
                console.error("Could not find audit table");
            }
            
            if (!document.getElementById('prevPage')) {
                console.error("Could not find previous page button");
            }
            
            if (!document.getElementById('nextPage')) {
                console.error("Could not find next page button");
            }

            // Initialize all modals with proper backdrop
            $('.modal').each(function() {
                var modalId = $(this).attr('id');
                $('#' + modalId).modal({
                    backdrop: true,
                    keyboard: true,
                    focus: true
                });
            });

            // Function to ensure modal backdrop is present
            function ensureModalBackdrop() {
                console.log('Ensuring modal backdrop...');
                
                // Check if any modal is currently visible
                const visibleModals = $('.modal.show');
                const hasVisibleModal = visibleModals.length > 0;
                
                // Check if backdrop exists
                const existingBackdrop = $('.modal-backdrop');
                const hasBackdrop = existingBackdrop.length > 0;
                
                console.log('Visible modals:', visibleModals.length, 'Existing backdrops:', existingBackdrop.length);
                
                // If there's a visible modal but no backdrop, create one
                if (hasVisibleModal && !hasBackdrop) {
                    console.log('Creating new backdrop for visible modal');
                    
                    // Create and append the backdrop
                    const backdrop = document.createElement('div');
                    backdrop.className = 'modal-backdrop show';
                    backdrop.style.opacity = '0.5';
                    backdrop.style.transition = 'none !important';
                    backdrop.style.animation = 'none !important';
                    document.body.appendChild(backdrop);
                    
                    // Ensure body has modal-open class
                    $('body').addClass('modal-open');
                    
                    return true;
                } 
                // If there's a backdrop but no visible modal, remove it
                else if (!hasVisibleModal && hasBackdrop) {
                    console.log('No visible modals, removing backdrop');
                    
                    // Check if any modal is in the process of being shown
                    const modalBeingShown = $('.modal').filter(function() {
                        return $(this).css('display') !== 'none' && !$(this).hasClass('show');
                    });
                    
                    if (modalBeingShown.length === 0) {
                        // Safe to remove backdrop
                        existingBackdrop.remove();
                        $('body').removeClass('modal-open');
                        $('body').css({
                            'overflow': '',
                            'padding-right': ''
                        });
                    } else {
                        console.log('Modal in transition, keeping backdrop');
                    }
                    
                    return false;
                }
                // If there's a visible modal and a backdrop, ensure correct styling
                else if (hasVisibleModal && hasBackdrop) {
                    console.log('Ensuring backdrop styling');
                    
                    // Ensure correct styling
                    existingBackdrop.addClass('show');
                    existingBackdrop.css({
                        'opacity': '0.5',
                        'transition': 'none !important',
                        'animation': 'none !important'
                    });
                    
                    // Ensure body has modal-open class
                    $('body').addClass('modal-open');
                    
                    return true;
                }
                
                return hasBackdrop;
            }
            
            // Function to clean up modal elements
            function cleanupModalElements() {
                // Hide any visible modals
                $('.modal').modal('hide');
                
                // Remove all modal backdrops
                $('.modal-backdrop').remove();
                
                // Remove modal open class and inline styles from body
                $('body').removeClass('modal-open');
                $('body').css('overflow', '');
                $('body').css('padding-right', '');
            }

            // Remove any custom pagination functions that might interfere with pagination.js
            // and replace with a compatible function
            function updatePaginationControls(visibleRows) {
                // This triggers the existing pagination.js updatePagination function
                if (typeof updatePagination === 'function') {
                    updatePagination();
                }
            }

            // Ensure scrolling is properly restored when any modal is closed
            $('.modal').on('hidden.bs.modal', function(e) {
                console.log('Modal hidden event triggered');
                
                // Only remove backdrop if there are no other visible modals
                if ($('.modal.show').length === 0) {
                    console.log('No visible modals, removing backdrop');
                    // Remove modal backdrop
                    $('.modal-backdrop').remove();
                    // Remove modal-open class and reset body styles
                    $('body').removeClass('modal-open');
                    $('body').css({
                        'overflow': '',
                        'padding-right': ''
                    });
                } else {
                    console.log('Other modals still visible, keeping backdrop');
                    // Ensure backdrop exists for other visible modals
                    ensureModalBackdrop();
                }
            });

            // Ensure modals are properly cleaned up when they're opened
            $('.modal').on('show.bs.modal', function(e) {
                console.log('Modal show event triggered for', this.id);
                
                // Don't remove existing backdrops if other modals are showing
                if ($('.modal.show').length === 0 && $('.modal-backdrop').length > 1) {
                    // Only remove excess backdrops
                    $('.modal-backdrop').not(':first').remove();
                }
                
                // Always ensure this modal has a backdrop
                ensureModalBackdrop();
            });
            
            // Add additional handler for backdrop clicks
            $(document).on('click', '.modal-backdrop', function(e) {
                console.log('Backdrop clicked');
                // Ensure proper cleanup when backdrop is clicked
                if ($('.modal.show').length === 0) {
                    $('.modal-backdrop').remove();
                    $('body').removeClass('modal-open');
                    $('body').css({
                        'overflow': '',
                        'padding-right': ''
                    });
                }
            });

            // Special handler for edit modal to ensure backdrop
            $('#editRoleModal').on('show.bs.modal', function() {
                ensureModalBackdrop();
            });

            if ($.fn.select2) {
                // Initialize Select2 for better dropdown experience
                $('#moduleFilter').select2({
                    placeholder: 'Select Module...',
                    allowClear: true,
                    width: '100%'
                });

                $('#privilegeFilter').select2({
                    placeholder: 'Select one or more privileges...',
                    allowClear: true,
                    width: '100%',
                    closeOnSelect: false,
                    multiple: true,
                    tags: false,
                    dropdownParent: $('#roleFilterForm'),
                    language: {
                        noResults: function() {
                            return "No privileges found";
                        },
                        searching: function() {
                            return "Searching...";
                        }
                    },
                    templateResult: function(data) {
                        if (data.loading) return data.text;
                        var $result = $('<span></span>');
                        $result.text(data.text);
                        return $result;
                    }
                }).on('select2:opening', function() {
                    // Focus the search field when dropdown opens
                    setTimeout(function() {
                        $('.select2-search__field').focus();
                    }, 100);
                });
            }

            // Add clear filters functionality
            $('#clearFilters, #inlineResetFilters').on('click', function() {
                // Redirect to the page without any query parameters
                window.location.href = window.location.pathname;
            });
            
            // Handle rows per page change
            $('#rowsPerPageSelect').on('change', function() {
                const rowsPerPage = $(this).val();
                const currentUrl = new URL(window.location.href);
                const params = new URLSearchParams(currentUrl.search);
                
                params.set('rows_per_page', rowsPerPage);
                params.set('page', '1'); // Reset to first page when changing rows per page
                
                window.location.href = `${currentUrl.pathname}?${params.toString()}`;
            });

            // No need for client-side pagination initialization since we're using server-side pagination

            // **1. Load edit role modal content via AJAX**
            $(document).on('click', '.edit-role-btn', function() {
                if (!userPrivileges.canModify) return;

                var roleID = $(this).data('role-id');
                $('#editRoleContent').html("Loading...");
                
                // Force backdrop to be visible
                setTimeout(function() {
                    ensureModalBackdrop();
                }, 50);
                
                $.ajax({
                    url: 'edit_roles.php',
                    type: 'GET',
                    data: {
                        id: roleID
                    },
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success: function(response) {
                        $('#editRoleContent').html(response);
                        $('#roleID').val(roleID);
                        
                        // Force backdrop to be visible again after content is loaded
                        setTimeout(function() {
                            ensureModalBackdrop();
                        }, 50);
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', status, error);
                        $('#editRoleContent').html('<p class="text-danger">Error loading role data. Please try again.</p>');
                    }
                });
            });

            // **2. Handle delete role modal**
            $('#confirmDeleteModal').on('show.bs.modal', function(event) {
                if (!userPrivileges.canRemove) {
                    event.preventDefault();
                    return false;
                }

                // Ensure this modal has a backdrop
                ensureModalBackdrop();

                var button = $(event.relatedTarget);
                var roleID = button.data('role-id');
                var roleName = button.data('role-name');
                $('#roleNamePlaceholder').text(roleName);
                $('#confirmDeleteButton').data('role-id', roleID);
            });

            // **3. Confirm delete role via AJAX**
            $(document).on('click', '#confirmDeleteButton', function(e) {
                if (!userPrivileges.canRemove) return;

                e.preventDefault();
                $(this).blur();
                var roleID = $(this).data('role-id');
                $.ajax({
                    type: 'POST',
                    url: 'delete_role.php',
                    data: {
                        id: roleID
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Close modal first to avoid UI issues
                            $('#confirmDeleteModal').modal('hide');
                            
                            // Only cleanup this specific modal without removing all backdrops
                            setTimeout(function() {
                                // Only remove backdrop if there are no other visible modals
                                if ($('.modal.show').length === 0) {
                                    $('.modal-backdrop').remove();
                                    $('body').removeClass('modal-open');
                                    $('body').css('overflow', '');
                                    $('body').css('padding-right', '');
                                }
                                
                                // Refresh the page to show updated data
                                window.location.reload();
                            }, 300);
                        } else {
                            showToast(response.message || 'An error occurred', 'error', 5000);
                        }
                    },
                    error: function(xhr, status, error) {
                        showToast('Error deleting role: ' + error, 'error', 5000);
                    }
                });
            });

            // **4. Load add role modal content**
            $('#addRoleModal').on('show.bs.modal', function(event) {
                if (!userPrivileges.canCreate) {
                    event.preventDefault();
                    return false;
                }

                // Ensure this modal has a backdrop
                ensureModalBackdrop();

                // Reset form if it exists
                if ($('#addRoleForm').length) {
                    $('#addRoleForm')[0].reset();
                    $('#addRoleForm').find('button[type="submit"]').prop('disabled', false);
                }

                $('#addRoleContent').html("Loading...");
                $.ajax({
                    url: 'add_role.php',
                    type: 'GET',
                    success: function(response) {
                        $('#addRoleContent').html(response);
                        
                        // Double-check backdrop after content is loaded
                        setTimeout(function() {
                            ensureModalBackdrop();
                        }, 50);
                    },
                    error: function() {
                        $('#addRoleContent').html('<p class="text-danger">Error loading form.</p>');
                    }
                });
            });
            
            // Handle add role modal hidden event
            $('#addRoleModal').on('hidden.bs.modal', function() {
                // Reset form if it exists
                if ($('#addRoleForm').length) {
                    $('#addRoleForm')[0].reset();
                    $('#addRoleForm').find('button[type="submit"]').prop('disabled', false);
                }
                
                // Ensure proper cleanup
                setTimeout(function() {
                    if ($('.modal.show').length === 0) {
                        $('.modal-backdrop').remove();
                        $('body').removeClass('modal-open');
                        $('body').css({
                            'overflow': '',
                            'padding-right': ''
                        });
                    }
                }, 100);
            });

            // **7. Undo button via AJAX**
            $(document).on('click', '#undoButton', function() {
                if (!userPrivileges.canUndo) return;

                $.ajax({
                    url: 'undo.php',
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Refresh the page to show updated data
                            window.location.reload();
                            showToast(response.message, 'success', 5000);
                        } else {
                            showToast(response.message || 'An error occurred', 'error', 5000);
                        }
                    },
                    error: function(xhr, status, error) {
                        showToast('Error processing undo request: ' + error, 'error', 5000);
                    }
                });
            });

            // **8. Redo button via AJAX**
            $(document).on('click', '#redoButton', function() {
                if (!userPrivileges.canRedo) return;

                $.ajax({
                    url: 'redo.php',
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Refresh the page to show updated data
                            window.location.reload();
                            showToast(response.message, 'success', 5000);
                        } else {
                            showToast(response.message || 'An error occurred', 'error', 5000);
                        }
                    },
                    error: function(xhr, status, error) {
                        showToast('Error processing redo request: ' + error, 'error', 5000);
                    }
                });
            });

            // Fix for modal backdrops - ensure they're properly initialized
            $('#editRoleModal, #confirmDeleteModal, #addRoleModal').each(function() {
                // Remove any existing modal backdrop configuration
                $(this).data('bs.modal', null);
                
                // Initialize with explicit backdrop option
                var modalOptions = {
                    backdrop: true,  // Set to 'static' to prevent closing when clicking outside
                    keyboard: true,
                    focus: true
                };
                
                try {
                    // Initialize the modal with Bootstrap 5 options
                    var bsModal = new bootstrap.Modal(this, modalOptions);
                    
                    // Store the modal instance for future reference
                    $(this).data('bs.modal', bsModal);
                } catch (e) {
                    console.error('Error initializing modal:', e);
                }
            });
            
            // Add global event listener for all modals to ensure backdrop
            $(document).on('shown.bs.modal', '.modal', function() {
                console.log('Modal fully shown, ensuring backdrop');
                ensureModalBackdrop();
            });
            
            // Add global event listener for when modal is about to be shown
            $(document).on('show.bs.modal', '.modal', function() {
                console.log('Modal about to show, ensuring backdrop');
                ensureModalBackdrop();
            });
            
            // Add event listener for when backdrop is clicked
            $(document).on('click', function(e) {
                // Check if the click was on the backdrop
                if ($(e.target).hasClass('modal-backdrop')) {
                    console.log('Backdrop clicked directly');
                    
                    // Force a small delay to let Bootstrap process the modal hiding
                    setTimeout(function() {
                        // If no modals are visible, remove backdrop
                        if ($('.modal.show').length === 0) {
                            $('.modal-backdrop').remove();
                            $('body').removeClass('modal-open');
                            $('body').css({
                                'overflow': '',
                                'padding-right': ''
                            });
                        } else {
                            // Otherwise ensure backdrop exists
                            ensureModalBackdrop();
                        }
                    }, 100);
                }
            });
            
            // Disable Bootstrap's default fade animations for modals
            $(document).ready(function() {
                // Override Bootstrap's modal animation
                if ($.fn.modal && $.fn.modal.Constructor && $.fn.modal.Constructor.prototype) {
                    try {
                        // Save original method
                        const originalBackdrop = $.fn.modal.Constructor.prototype._backdrop;
                        
                        // Override the backdrop method
                        $.fn.modal.Constructor.prototype._backdrop = function(callback) {
                            console.log('Custom backdrop method called');
                            
                            // Call original method first
                            if (typeof originalBackdrop === 'function') {
                                originalBackdrop.call(this, callback);
                            }
                            
                            // Then ensure our custom backdrop styling
                            ensureModalBackdrop();
                            
                            // Call the callback if provided
                            if (typeof callback === 'function') {
                                callback();
                            }
                        };
                    } catch (e) {
                        console.error('Error overriding Bootstrap modal:', e);
                    }
                }
            });
            
            // Add a global click handler for modal triggers
            $(document).on('click', '[data-bs-toggle="modal"]', function() {
                console.log('Modal trigger clicked');
                
                // Force backdrop creation on next tick
                setTimeout(function() {
                    ensureModalBackdrop();
                }, 10);
            });

            // Add a MutationObserver to monitor when modals are added to the DOM
            $(document).ready(function() {
                // Create a MutationObserver to monitor for modal changes
                const modalObserver = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        // Check for added nodes
                        if (mutation.addedNodes.length) {
                            mutation.addedNodes.forEach(function(node) {
                                // Check if the added node is a modal or contains a modal
                                if (node.classList && node.classList.contains('modal')) {
                                    console.log('Modal added to DOM:', node.id);
                                    ensureModalBackdrop();
                                } else if (node.querySelectorAll) {
                                    const modals = node.querySelectorAll('.modal');
                                    if (modals.length) {
                                        console.log('Modal found inside added node');
                                        ensureModalBackdrop();
                                    }
                                }
                            });
                        }
                        
                        // Check for attribute changes on modals
                        if (mutation.type === 'attributes' && 
                            mutation.target.classList && 
                            mutation.target.classList.contains('modal')) {
                            if (mutation.attributeName === 'class' && 
                                mutation.target.classList.contains('show')) {
                                console.log('Modal class changed to show');
                                ensureModalBackdrop();
                            }
                        }
                    });
                });

                // Start observing the document body for modal-related changes
                modalObserver.observe(document.body, { 
                    childList: true,
                    subtree: true,
                    attributes: true,
                    attributeFilter: ['class']
                });
                
                // Also ensure backdrop when page is fully loaded
                setTimeout(function() {
                    if ($('.modal.show').length > 0) {
                        console.log('Modal found on page load');
                        ensureModalBackdrop();
                    }
                }, 500);
            });

            // Add global event listener for when modal is hidden
            $(document).on('hidden.bs.modal', '.modal', function() {
                console.log('Modal hidden, checking if backdrop should be removed');
                
                // Use setTimeout to allow all modal events to complete
                setTimeout(function() {
                    // Check if any modals are still visible
                    if ($('.modal.show').length === 0) {
                        console.log('No visible modals after hiding, removing backdrop');
                        $('.modal-backdrop').remove();
                        $('body').removeClass('modal-open');
                        $('body').css({
                            'overflow': '',
                            'padding-right': ''
                        });
                    } else {
                        console.log('Other modals still visible, keeping backdrop');
                        ensureModalBackdrop();
                    }
                }, 100);
            });
            
            // Add global event listener for when modal is about to be hidden
            $(document).on('hide.bs.modal', '.modal', function() {
                console.log('Modal about to hide');
                
                // Store the number of visible modals before this one hides
                const visibleModalsCount = $('.modal.show').length;
                $(this).data('visibleModalsBeforeHide', visibleModalsCount);
                
                // If this is the last visible modal, mark it for potential backdrop removal
                if (visibleModalsCount === 1 && $(this).hasClass('show')) {
                    console.log('Last visible modal is being hidden');
                    $(this).data('lastVisibleModal', true);
                }
            });

            // Add direct event handler for backdrop clicks
            $(document).ready(function() {
                // Use event delegation for dynamically added backdrops
                $(document).on('mousedown touchstart', '.modal-backdrop', function(e) {
                    console.log('Direct backdrop click detected');
                    
                    // Get the currently visible modal(s)
                    const visibleModals = $('.modal.show');
                    
                    if (visibleModals.length > 0) {
                        console.log('Visible modals found when backdrop clicked:', visibleModals.length);
                        
                        // Mark that this was a direct backdrop click
                        $(document).data('backdropDirectClick', true);
                        
                        // Store timestamp of the click
                        $(document).data('backdropClickTime', new Date().getTime());
                    }
                });
                
                // Add a global document click handler to detect clicks outside modal content
                $(document).on('mousedown touchstart', function(e) {
                    // Check if click was outside any modal content
                    if ($(e.target).closest('.modal-content').length === 0 && 
                        $('.modal.show').length > 0 && 
                        !$(e.target).hasClass('modal-backdrop')) {
                        
                        console.log('Click outside modal content detected');
                        
                        // Force check for backdrop on next tick
                        setTimeout(function() {
                            ensureModalBackdrop();
                        }, 100);
                    }
                });
                
                // Special handler for ESC key (which can also close modals)
                $(document).on('keydown', function(e) {
                    if (e.key === 'Escape' && $('.modal.show').length > 0) {
                        console.log('ESC key pressed with modal open');
                        
                        // Force check for backdrop on next tick
                        setTimeout(function() {
                            ensureModalBackdrop();
                        }, 100);
                    }
                });
            });
        });
    </script>
</body>

</html>