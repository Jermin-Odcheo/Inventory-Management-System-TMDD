<?php
/**
 * @file sidebar.php
 * @brief handles the display of the sidebar menu
 *
 * This script handles the display of the sidebar menu. It checks user permissions,
 * fetches and filters audit log data based on various criteria, and formats the data for presentation in a user interface.
 */
declare(strict_types=1);

require_once(__DIR__ . '/../../../../config/ims-tmdd.php');
require_once(__DIR__ . '/../../../control/RBACService.php');
/**
 * @var int|null $userId The user ID of the current user.
 */
$userId = $_SESSION['user_id'] ?? null;
/**
 * If the user is not logged in, the script end.
 */
if (!$userId) {
    die('User not logged in');
}
/**
 * @var RBACService $rbac The RBAC service instance.
 */
$rbac = new RBACService($pdo, $userId);

/**
 * @var array $modules The modules to display in the sidebar.
 */
$modules = [
    'User Management' => ['audit' => 'um_audit_log.php', 'archive' => 'archive.php', 'user management' => ''],
    'Equipment Management' => ['audit' => 'em_audit_log.php', 'archive' => 'em_archive.php'],
    'Equipment Transactions' => ['audit' => 'et_audit_log.php', 'archive' => 'et_archive.php'],
    'Roles and Privileges' => ['audit' => 'rm_audit_log.php', 'archive' => 'rm_archive.php'],
    'Management' => ['audit' => 'department_audit_log.php', 'archive' => 'department_archive.php'],
];

/**
 * @var array $auditModules The audit modules to display in the sidebar.
 *
 * @var array $archiveModules The archive modules to display in the sidebar.
 */
$auditModules = [];
$archiveModules = [];

/**
 * @var bool $hasGlobalAuditTrack The flag indicating if the user has global Track privilege on Audit module.
 */
$hasGlobalAuditTrack = $rbac->hasPrivilege('Audit', 'Track');

/**
 * @var array $auditModules The audit modules to display in the sidebar.
 */
foreach ($modules as $module => $paths) {
    if ($hasGlobalAuditTrack) {
        // User has global Audit Track, so include all modules audit logs
        $auditModules[$module] = $paths['audit'];
    } else {
        // No global Audit Track, only include audit logs if user has Track on the specific module
        if ($rbac->hasPrivilege($module, 'Track')) {
            $auditModules[$module] = $paths['audit'];
        }
    }

    // Archives logic stays the same (depends on View + other privs)
    $hasActionPriv = array_filter(
        ['Restore', 'Remove', 'Permanently Delete'],
        fn($priv) => $rbac->hasPrivilege($module, $priv)
    );

    if ($rbac->hasPrivilege($module, 'View') && !empty($hasActionPriv)) {
        $archiveModules[$module] = $paths['archive'];
    }
}

/**
 * Summary of getAcronym
 * @param mixed $string
 * @return string
 */
function getAcronym($string) {
    $words = preg_split("/[\s_]+/", $string);
    $acronym = '';
    foreach ($words as $word) {
        $acronym .= strtoupper($word[0]);
    }
    return $acronym;
}

?>
<button class="sidebar-toggle" id="sidebarToggle">
    <i class="fas fa-bars"></i>
</button>

<div class="sidebar" id="sidebar">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>src/view/styles/css/sidebar.css">

    <h3>Menu</h3>
    <nav>
        <ul>
            <li>
                <a href="<?php echo BASE_URL; ?>src/view/php/clients/dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>

            <?php if (!empty($auditModules)): ?>
                <li class="dropdown-item">
                    <button class="dropdown-toggle" onclick="this.nextElementSibling.classList.toggle('show')">
                        <i class="fas fa-history"></i><span class="menu-text">Audit Logs</span>
                        <i class="fas fa-chevron-down dropdown-icon"></i>
                    </button>
                    <ul class="dropdown tree">
                        <?php foreach ($auditModules as $module => $file): ?>
                            <i class="bi bi-file-earmark-check">
                                <a href="<?= BASE_URL ?>src/view/php/modules/log_management/audit_manager/<?= $file ?>">
                                    <span class="short-label"><?= getAcronym($module) ?>AL</span>
                                </a>
                            </i>
                            <li class="nav-item">
                                <a href="<?= BASE_URL ?>src/view/php/modules/log_management/audit_manager/<?= $file ?>">
                                    <?= $module ?> Audit Logs
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </li>
            <?php endif; ?>

            <?php if (!empty($archiveModules)): ?>
                <li class="dropdown-item">
                    <button class="dropdown-toggle" onclick="this.nextElementSibling.classList.toggle('show')">
                        <i class="fas fa-trash"></i><span class="menu-text">Archives</span>
                        <i class="fas fa-chevron-down dropdown-icon"></i>
                    </button>
                    <ul class="dropdown tree">
                        <?php foreach ($archiveModules as $module => $file): ?>
                            <i class="bi bi-file-earmark-check">
                                <a href="<?= BASE_URL ?>src/view/php/modules/log_management/archive_manager/<?= $file ?>">
                                    <span class="short-label"><?= getAcronym($module) ?>A</span>
                                </a>
                            </i>
                            <li class="nav-item">
                                <a href="<?= BASE_URL ?>src/view/php/modules/log_management/archive_manager/<?= $file ?>">
                                    <?= $module ?> Archives
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </li>
            <?php endif; ?>

            <?php if ($rbac->hasPrivilege('Administration', 'View')): ?>
                <li class="dropdown-item">
                    <button class="dropdown-toggle" onclick="this.nextElementSibling.classList.toggle('show')">
                        <i class="fas fa-university"></i><span class="menu-text">Administration</span>
                        <i class="fas fa-chevron-down dropdown-icon"></i>
                    </button>
                    <ul class="dropdown tree">
                        <i class="bi bi-file-earmark-check">
                            <a href="<?= BASE_URL ?>src/view/php/modules/management/department_manager/department_management.php<?= $file ?>">
                                <span class="short-label"><?= getAcronym($module) ?>DM</span>
                            </a>
                        </i>
                        <li class="nav-item">
                            <a href="<?php echo BASE_URL; ?>src/view/php/modules/management/department_manager/department_management.php"
                                class="nav-link">
                                <span class="submenu-text">Department Management</span>
                            </a>
                        </li>
                    </ul>
                </li>
            <?php endif; ?>

            <?php if ($rbac->hasPrivilege('User Management', 'View')): ?>
                <li class="dropdown-item">
                    <button class="dropdown-toggle" onclick="this.nextElementSibling.classList.toggle('show')">
                        <i class="fa-solid fa-user"></i>
                        <span class="menu-text">User Management</span>
                        <i class="fas fa-chevron-down dropdown-icon"></i>
                    </button>
                    <ul class="dropdown tree">
                        <i class="bi bi-file-earmark-check">
                            <a href="<?= BASE_URL ?>src/view/php/modules/user_manager/user_management.php">
                                <span class="short-label">MA</span>
                            </a>
                        </i>
                        <li class="nav-item">
                            <a href="<?php echo BASE_URL; ?>src/view/php/modules/user_manager/user_management.php"
                                class="nav-link">
                                <span class="submenu-text">Manage Accounts</span>
                            </a>
                        </li>
                        <i class="bi bi-file-earmark-check">
                            <a href="<?= BASE_URL ?>src/view/php/modules/user_manager/user_roles_management.php">
                                <span class="short-label">URM</span>
                            </a>
                        </i>
                        <li class="nav-item">
                            <a href="<?php echo BASE_URL; ?>src/view/php/modules/user_manager/user_roles_management.php"
                                class="nav-link">
                                <span class="submenu-text">User Roles Management</span>
                            </a>
                        </li>
                    </ul>
                </li>
            <?php endif; ?>

            <?php if ($rbac->hasPrivilege('Roles and Privileges', 'View')): ?>
                <li class="dropdown-item">
                    <button class="dropdown-toggle" onclick="this.nextElementSibling.classList.toggle('show')">
                        <i class="fa-solid fa-th-list"></i>
                        <span class="menu-text">Roles and Privileges</span>
                        <i class="fas fa-chevron-down dropdown-icon"></i>
                    </button>
                    <ul class="dropdown tree">
                        <i class="bi bi-file-earmark-check">
                            <a href="<?= BASE_URL ?>src/view/php/modules/rolesandprivilege_manager/role_manager/manage_roles.php">
                                <span class="short-label">RPM</span>
                            </a>
                        </i>
                        <li class="nav-item">
                            <a href="<?php echo BASE_URL; ?>src/view/php/modules/rolesandprivilege_manager/role_manager/manage_roles.php"
                                class="nav-link">
                                <span class="submenu-text">Roles and Privileges Management</span>
                            </a>
                        </li>
                        <!-- <li class="nav-item">
                        <a href="<?php echo BASE_URL; ?>src/view/php/modules/rolesandprivilege_manager/privilege_manager/manage_privileges.php" class="nav-link">
                            <span class="submenu-text">Privilege Management <br> 🆕💻 (Prototype)</span>
                        </a>
                    </li> -->
                    </ul>
                </li>
            <?php endif; ?>

            <?php if ($rbac->hasPrivilege('Equipment Management', 'View')): ?>
                <li class="dropdown-item">
                    <button class="dropdown-toggle" onclick="this.nextElementSibling.classList.toggle('show')">
                        <i class="fa-solid fa-wrench"></i>
                        <span class="menu-text">Equipment Management</span>
                        <i class="fas fa-chevron-down dropdown-icon"></i>
                    </button>
                    <ul class="dropdown tree">
                        <i class="bi bi-file-earmark-check">
                            <a href="<?= BASE_URL ?>src/view/php/modules/equipment_manager/equipment_details.php">
                                <span class="short-label">ED</span>
                            </a>
                        </i>
                        <li><a href="<?php echo BASE_URL; ?>src/view/php/modules/equipment_manager/equipment_details.php">Equipment
                                Details</a></li>
                        <i class="bi bi-file-earmark-check">
                            <a href="<?= BASE_URL ?>src/view/php/modules/equipment_manager/equipment_location.php">
                                <span class="short-label">EL</span>
                            </a>
                        </i>
                        <li><a href="<?php echo BASE_URL; ?>src/view/php/modules/equipment_manager/equipment_location.php">Equipment
                                Location</a></li>
                        <i class="bi bi-file-earmark-check">
                            <a href="<?= BASE_URL ?>src/view/php/modules/equipment_manager/equipment_status.php">
                                <span class="short-label">ES-PMS</span>
                            </a>
                        </i>
                        <li><a href="<?php echo BASE_URL; ?>src/view/php/modules/equipment_manager/equipment_status.php">Equipment
                                Status for PMS</a></li>
                    </ul>
                </li>
            <?php endif; ?>

            <?php if ($rbac->hasPrivilege('Equipment Transactions', 'View')): ?>
                <li class="dropdown-item">
                    <button class="dropdown-toggle" onclick="this.nextElementSibling.classList.toggle('show')">
                        <i class="fa-solid fa-arrow-right-arrow-left"></i> <span class="menu-text">Equipment
                            Transaction</span>
                        <i class="fas fa-chevron-down dropdown-icon"></i>
                    </button>
                    <ul class="dropdown tree">
                        <li><a href="<?php echo BASE_URL; ?>src/view/php/modules/equipment_transactions/purchase_order.php">Purchase
                                Order</a></li>
                        <li><a href="<?php echo BASE_URL; ?>src/view/php/modules/equipment_transactions/charge_invoice.php">Charge
                                Invoice</a></li>
                        <li><a
                                href="<?php echo BASE_URL; ?>src/view/php/modules/equipment_transactions/receiving_report.php">Receiving
                                Report</a></li>
                    </ul>
                </li>
            <?php endif; ?>

            <?php if ($rbac->hasPrivilege('Reports', 'View')): ?>
                <li class="dropdown-item">
                    <button class="dropdown-toggle" onclick="this.nextElementSibling.classList.toggle('show')">
                        <i class="fas fa-flag"></i> <span class="menu-text">Reports</span>
                        <i class="fas fa-chevron-down dropdown-icon"></i>
                    </button>
                    <ul class="dropdown tree">
                        <!-- <li><a href="<?php echo BASE_URL; ?>src/view/php/modules/reports/userman_reports/usrep.php">User Management Reports 🔜 (Under Development)</a></li> -->
                        <li><a href="<?php echo BASE_URL; ?>src/view/php/modules/reports/equipman_reports/eqrep.php">Equipment
                                Management Report </a></li>
                    </ul>
                </li>
            <?php endif; ?>

        </ul>
    </nav>
</div>

<script>
    // Sidebar toggle functionality
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const header = document.querySelector('.header');
    const mainContent = document.querySelector('.main-content');

    // Function to update all collapsed states
    function updateCollapsedState(isCollapsed) {
        sidebar.classList.toggle('collapsed', isCollapsed);
        header?.classList.toggle('sidebar-collapsed', isCollapsed);
        if (mainContent) {
            mainContent.classList.toggle('sidebar-collapsed', isCollapsed);
            mainContent.style.marginLeft = isCollapsed ? '60px' : '300px';
        }
        localStorage.setItem('sidebarCollapsed', isCollapsed);
    }

    // Function to check screen size and collapse if needed
    function checkScreenSize() {
        if (window.innerWidth <= 768) {
            updateCollapsedState(true);
        } else {
            const isSidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            updateCollapsedState(isSidebarCollapsed);
        }
    }

    // Initial check
    checkScreenSize();

    // Listen for window resize
    window.addEventListener('resize', checkScreenSize);

    // Check if there's a saved state in localStorage
    const isSidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    if (isSidebarCollapsed) {
        updateCollapsedState(true);
    }

    sidebarToggle.addEventListener('click', () => {
        const willBeCollapsed = !sidebar.classList.contains('collapsed');
        updateCollapsedState(willBeCollapsed);
    });

    // Existing dropdown functionality
    document.querySelectorAll('.dropdown-toggle').forEach(btn => {
        btn.addEventListener('click', () => {
            const expanded = btn.getAttribute('aria-expanded') === 'true';
            btn.setAttribute('aria-expanded', !expanded);
            const dropdownMenu = btn.nextElementSibling;
            if (dropdownMenu && dropdownMenu.classList.contains('dropdown')) {
                dropdownMenu.setAttribute('aria-expanded', !expanded);
            }
        });
    });

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
            toggle.addEventListener('click', () => {
                toggle.style.background = 'transparent';
                toggle.style.transform = 'none';
                toggle.style.textShadow = 'none';
                toggle.style.boxShadow = 'none';
            });
        });
    });
</script>