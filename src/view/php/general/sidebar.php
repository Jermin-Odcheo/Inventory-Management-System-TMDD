<?php
declare(strict_types=1);

require_once(__DIR__ . '/../../../../config/ims-tmdd.php');
require_once(__DIR__ . '/../../../control/RBACService.php');

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    die('User not logged in');
}

$rbac = new RBACService($pdo, $userId);

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

            <?php if ($rbac->hasPrivilege('Audit', 'View')): ?>
            <li class="dropdown-item">
                <button class="dropdown-toggle" aria-expanded="false">
                    <i class="fas fa-history"></i> Logs
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </button>
                <ul class="dropdown tree" aria-expanded="false">
                    <ul><b> Audit Logs </b><br><hr>
                        <li><a href="<?php echo BASE_URL; ?>src/view/php/modules/audit_manager/audit_log.php">User Management Audit Logs</a></li>
                        <li><a href="<?php echo BASE_URL; ?>src/view/php/modules/audit_manager/em_audit_log.php">Equipment Management Audit Logs</a></li>
                        <li><a href="<?php echo BASE_URL; ?>src/view/php/modules/audit_manager/et_audit_log.php">Equipment Transaction Audit Logs</a></li>
                        <li><a href="<?php echo BASE_URL; ?>src/view/php/modules/audit_manager/rm_audit_log.php">Role Management Audit Logs</a></li>
                        <li><a href="<?php echo BASE_URL; ?>src/view/php/modules/audit_manager/department_audit_log.php">Department Management Audit Logs</a></li>
                    </ul>
                    <ul><b> Archives </b><br><hr>
                        <li><a href="<?php echo BASE_URL; ?>src/view/php/modules/audit_manager/archive.php">User Management Archives</a></li>
                        <li><a href="<?php echo BASE_URL; ?>src/view/php/modules/audit_manager/em_archive.php">Equipment Management Archives</a></li>
                        <li><a href="<?php echo BASE_URL; ?>src/view/php/modules/audit_manager/et_archive.php">Equipment Transactions Archives</a></li>
                        <li><a href="<?php echo BASE_URL; ?>src/view/php/modules/audit_manager/rm_archive.php">Role Management Archives</a></li>
                        <li><a href="<?php echo BASE_URL; ?>src/view/php/modules/audit_manager/department_archive.php">Department Management Archives</a></li>
                    </ul>
                </ul>
            </li>
            <?php endif; ?>

            <?php if ($rbac->hasPrivilege('Management', 'View')): ?>
            <li class="dropdown-item">
                <button class="dropdown-toggle" aria-expanded="false">
                    <i class="fas fa-history"></i> Management
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </button>
                <ul class="dropdown tree" aria-expanded="false">
                <li class="nav-item">
                        <a href="<?php echo BASE_URL; ?>src/view/php/modules/role_manager/department_management.php" class="nav-link">
                            <span class="submenu-text">Department Management</span>
                        </a>
                    </li>
                </ul>
            </li>
            <?php endif; ?>

            <?php if ($rbac->hasPrivilege('User Management', 'View')): ?>
            <li class="dropdown-item">
                <button class="dropdown-toggle" aria-expanded="false">
                    <i class="fa-solid fa-user"></i>
                    <span class="menu-text">User Management</span>
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </button>
                <ul class="dropdown tree" aria-expanded="false">
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
                <button class="dropdown-toggle" aria-expanded="false">
                    <i class="fa-solid fa-user"></i>
                    <span class="menu-text">Roles and Privileges</span>
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </button>
                <ul class="dropdown tree" aria-expanded="false">
                    <li class="nav-item">
                        <a href="<?php echo BASE_URL; ?>src/view/php/modules/role_manager/manage_roles.php" class="nav-link">
                            <span class="submenu-text">Role Management</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo BASE_URL; ?>src/view/php/modules/privilege_manager/manage_privileges.php" class="nav-link">
                            <span class="submenu-text">Privilege Management <br> 🆕💻 (Prototype)</span>
                        </a>
                    </li>
                </ul>
            </li>
            <?php endif; ?>

            <?php if ($rbac->hasPrivilege('Equipment Management', 'View')): ?>
            <li class="dropdown-item">
                <button class="dropdown-toggle" aria-expanded="false">
                    <i class="fa-solid fa-wrench"></i> Equipment Management
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </button>
                <ul class="dropdown tree" aria-expanded="false">
                    <li><a href="<?php echo BASE_URL; ?>src/view/php/modules/equipment_manager/equipment_details.php">Equipment Details</a></li>
                    <li><a href="<?php echo BASE_URL; ?>src/view/php/modules/equipment_manager/equipment_location.php">Equipment Location</a></li>
                    <li><a href="<?php echo BASE_URL; ?>src/view/php/modules/equipment_manager/equipment_status.php">Equipment Status for PMS</a></li>
                </ul>
            </li>
            <?php endif; ?>

            <?php if ($rbac->hasPrivilege('Equipment Transactions', 'View')): ?>
            <li class="dropdown-item">
                <button class="dropdown-toggle" aria-expanded="false">
                    <i class="fa-solid fa-arrow-right-arrow-left"></i> Equipment Transaction
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </button>
                <ul class="dropdown tree" aria-expanded="false">
                    <li><a href="<?php echo BASE_URL; ?>src/view/php/modules/equipment_manager/purchase_order.php">Purchase Order</a></li>
                    <li><a href="<?php echo BASE_URL; ?>src/view/php/modules/equipment_manager/charge_invoice.php">Charge Invoice</a></li>
                    <li><a href="<?php echo BASE_URL; ?>src/view/php/modules/equipment_manager/receiving_report.php">Receiving Report</a></li>
                </ul>
            </li>
            <?php endif; ?>

            <?php if ($rbac->hasPrivilege('Reports', 'View')): ?>
            <li class="dropdown-item">
                <button class="dropdown-toggle" aria-expanded="false">
                    <i class="fas fa-flag"></i> Reports 🔜 (Under Development)
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </button>
                <ul class="dropdown tree" aria-expanded="false">
                    <li><a href="#">User Management Reports 🔜 (Under Development)</a></li>
                    <li><a href="#">Equipment Status Reports 🔜 (Under Development)</a></li>
                    <li><a href="#">Equipment Transaction Reports 🔜 (Under Development)</a></li>
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
