<?php
declare(strict_types=1);

require_once(__DIR__ . '/../../../../config/ims-tmdd.php');
require_once(__DIR__ . '/../../../control/RBACService.php');

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    die('User not logged in');
}

$rbac = new RBACService($pdo, $userId);

$modules = [
    'User Management' => ['audit' => 'audit_log.php', 'archive' => 'archive.php', 'user management' => ''],
    'Equipment Management' => ['audit' => 'em_audit_log.php', 'archive' => 'em_archive.php'],
    'Equipment Transactions' => ['audit' => 'et_audit_log.php', 'archive' => 'et_archive.php'],
    'Roles and Privileges' => ['audit' => 'rm_audit_log.php', 'archive' => 'rm_archive.php'],
    'Management' => ['audit' => 'department_audit_log.php', 'archive' => 'department_archive.php'],
];

$auditModules = [];
$archiveModules = [];

// Check if user has global Track privilege on Audit module
$hasGlobalAuditTrack = $rbac->hasPrivilege('Audit', 'Track');

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


?>

<div class="sidebar">
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
                        <li class="nav-item">
                            <a href="<?= BASE_URL ?>src/view/php/modules/log_management/archive_manager/<?= $file ?>">
                                <?= $module ?> Archives
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </li>
            <?php endif; ?>

            <?php if ($rbac->hasPrivilege('Management', 'View')): ?>
            <li class="dropdown-item">
            <button class="dropdown-toggle" onclick="this.nextElementSibling.classList.toggle('show')">
            <i class="fas fa-university"></i><span class="menu-text">Management</span>
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </button>
                <ul class="dropdown tree">
                <li class="nav-item">
                        <a href="<?php echo BASE_URL; ?>src/view/php/modules/management/department_manager/department_management.php" class="nav-link">
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
                    <li class="nav-item">
                        <a href="<?php echo BASE_URL; ?>src/view/php/modules/user_manager/user_management.php" class="nav-link">
                            <span class="submenu-text">Manage Accounts</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo BASE_URL; ?>src/view/php/modules/user_manager/user_roles_management.php" class="nav-link">
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
                    <li class="nav-item">
                        <a href="<?php echo BASE_URL; ?>src/view/php/modules/rolesandprivilege_manager/role_manager/manage_roles.php" class="nav-link">
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
                    <li><a href="<?php echo BASE_URL; ?>src/view/php/modules/equipment_manager/equipment_details.php">Equipment Details</a></li>
                    <li><a href="<?php echo BASE_URL; ?>src/view/php/modules/equipment_manager/equipment_location.php">Equipment Location</a></li>
                    <li><a href="<?php echo BASE_URL; ?>src/view/php/modules/equipment_manager/equipment_status.php">Equipment Status for PMS</a></li>
                </ul>
            </li>
            <?php endif; ?>

            <?php if ($rbac->hasPrivilege('Equipment Transactions', 'View')): ?>
            <li class="dropdown-item">
            <button class="dropdown-toggle" onclick="this.nextElementSibling.classList.toggle('show')">                    
                <i class="fa-solid fa-arrow-right-arrow-left"></i> <span class="menu-text">Equipment Transaction</span>
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </button>
                <ul class="dropdown tree">
                    <li><a href="<?php echo BASE_URL; ?>src/view/php/modules/equipment_transactions/purchase_order.php">Purchase Order</a></li>
                    <li><a href="<?php echo BASE_URL; ?>src/view/php/modules/equipment_transactions/charge_invoice.php">Charge Invoice</a></li>
                    <li><a href="<?php echo BASE_URL; ?>src/view/php/modules/equipment_transactions/receiving_report.php">Receiving Report</a></li>
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
                    <li><a href="<?php echo BASE_URL; ?>src/view/php/modules/reports/equipman_reports/eqrep.php">Equipment Management Report </a></li>
                </ul>
            </li>
            <?php endif; ?>

        </ul>
    </nav>
</div>

<script>
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
