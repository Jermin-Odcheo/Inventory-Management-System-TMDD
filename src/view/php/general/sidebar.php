<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once(__DIR__ . '/../../../../config/config.php');
$role = isset($_SESSION['role']) ? $_SESSION['role'] : '';
?>
<!-- Sidebar -->
<div class="sidebar">
    <!-- Font Awesome CSS (Ideally load this in your <head> for performance) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>src/view/styles/css/sidebar.css">
    <h2>Inventory Management System</h2>
    <h3>Menu</h3>
    <nav>
        <ul>
            <li>
                <a href="<?php echo BASE_URL; ?>src/view/php/clients/admins/dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>

            <!-- Example: Only show User Management for Super Admins & Admins -->
            <?php if ($role === 'Super Admin' || $role === 'Admin'): ?>
                <!-- Add admin-specific links here -->
            <?php endif; ?>

            <li class="dropdown-item">
                <a href="#" class="dropdown-toggle">
                    <i class="fas fa-history"></i> Logs
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </a>
                <ul class="dropdown">
                    <li>
                        <a href="<?php echo BASE_URL; ?>src/view/php/clients/admins/audit_log.php">
                            <i class="fas fa-history"></i> Audit Logs
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo BASE_URL; ?>src/view/php/clients/admins/archive.php">
                            <i class="fas fa-archive"></i> Archives
                        </a>
                    </li>
                </ul>
            </li>

            <li class="dropdown-item">
                <a href="#" class="dropdown-toggle">
                    <i class="fa-solid fa-user"></i> User Management
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </a>
                <ul class="dropdown">
                    <li>
                        <a href="<?php echo BASE_URL; ?>src/view/php/modules/user_manager/user_management.php">
                            Manage Accounts
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo BASE_URL; ?>src/view/php/modules/role_manager/manage_roles.php">
                            Manage Roles
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo BASE_URL; ?>src/view/php/modules/role_manager/manage_privilege.php">
                            Manage Privileges
                        </a>
                    </li>
                </ul>
            </li>

            <li class="dropdown-item">
                <a href="#" class="dropdown-toggle">
                    <i class="fa-solid fa-wrench"></i> Equipment Management
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </a>
                <ul class="dropdown">
                    <li>
                        <a href="../../modules/equipment_manager/purchase_order.php">
                            Purchase Order
                        </a>
                    </li>
                    <li>
                        <a href="../../modules/equipment_manager/charge_invoice.php">
                            Charge Invoice
                        </a>
                    </li>
                    <li>
                        <a href="../../modules/equipment_manager/receiving_report.php">
                            Receiving Report
                        </a>
                    </li>
                    <li>
                        <a href="../../modules/equipment_manager/equipment_details.php">
                            Equipment Details
                        </a>
                    </li>
                    <li>
                        <a href="../../modules/equipment_manager/equipment_location.php">
                            Equipment Location
                        </a>
                    </li>
                    <li>
                        <a href="../../modules/equipment_manager/equipment_status.php">
                            Equipment Status
                        </a>
                    </li>
                </ul>
            </li>

            <li>
                <a href="settings.php">
                    <i class="fas fa-cogs"></i> Settings
                </a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>src/view/php/general/logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>
    </nav>
</div>

<!-- JavaScript to handle dropdown interactions -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var dropdownToggles = document.querySelectorAll('.dropdown-toggle');
        dropdownToggles.forEach(function (toggle) {
            toggle.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var parentLi = this.parentElement;
                parentLi.classList.toggle('open');

                // Rotate the dropdown arrow
                var icon = this.querySelector('.dropdown-icon');
                if (parentLi.classList.contains('open')) {
                    icon.style.transform = 'rotate(180deg)';
                } else {
                    icon.style.transform = 'rotate(0deg)';
                }
            });
        });

        // Close dropdowns when clicking outside the sidebar
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.sidebar')) {
                document.querySelectorAll('.dropdown-item.open').forEach(function (item) {
                    item.classList.remove('open');
                    var icon = item.querySelector('.dropdown-icon');
                    if (icon) icon.style.transform = 'rotate(0deg)';
                });
            }
        });
    });
</script>
