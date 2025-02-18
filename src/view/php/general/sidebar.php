<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once(__DIR__ . '/../../../../config/config.php');
$role = isset($_SESSION['role']) ? $_SESSION['role'] : '';
?>
<!-- Sidebar -->
<div class="sidebar">
    <!-- Font Awesome & Sidebar CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>src/view/styles/css/sidebar.css">

    <h2>Inventory Management System</h2>
    <h2>Menu</h2>
    <nav>
        <ul>
            <li>
                <a href="<?php echo BASE_URL; ?>src/view/php/clients/admins/dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>

            <!-- Example: Only show User Management for Super Admins & Admins -->
            <?php if ($role === 'Super Admin' || $role === 'Admin'): ?>
                <!-- Admin-specific links could go here -->
            <?php endif; ?>

            <li>
                <a href="#" class="dropdown-toggle">
                    <i class="fas fa-history"></i> Logs
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

            <li>
                <a href="#" class="dropdown-toggle">
                    <i class="fa-solid fa-user"></i> User Management
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

            <li>
                <a href="#" class="dropdown-toggle">
                    <i class="fa-solid fa-wrench"></i> Equipment Management
                </a>
                <ul class="dropdown">
                    <li><a href="../../modules/equipment_manager/purchase_order.php">Purchase Order</a></li>
                    <li><a href="../../modules/equipment_manager/charge_invoice.php">Charge Invoice</a></li>
                    <li><a href="../../modules/equipment_manager/receiving_report.php">Receiving Report</a></li>
                    <li><a href="../../modules/equipment_manager/equipment_details.php">Equipment Details</a></li>
                    <li><a href="../../modules/equipment_manager/equipment_location.php">Equipment Location</a></li>
                    <li><a href="../../modules/equipment_manager/equipment_status.php">Equipment Status</a></li>
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
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Handle clicks on dropdown toggles
        var toggles = document.querySelectorAll('.dropdown-toggle');
        toggles.forEach(function (toggle) {
            toggle.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation(); // Prevent click from bubbling to document
                var li = this.parentElement;

                // If already clicked (locked open), remove the lock and close the dropdown.
                // Also add a temporary class to disable immediate hover reopening.
                if (li.classList.contains('clicked')) {
                    li.classList.remove('clicked', 'open');
                    li.classList.add('disable-hover');
                } else {
                    // Otherwise, lock it open via click.
                    li.classList.add('clicked', 'open');
                }
            });
        });

        // When the mouse leaves the dropdown item, remove the temporary disable-hover class.
        var listItems = document.querySelectorAll('.sidebar nav ul li');
        listItems.forEach(function (li) {
            li.addEventListener('mouseleave', function () {
                li.classList.remove('disable-hover');
            });
        });

        // Clicking anywhere outside the sidebar resets all dropdowns back to default.
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.sidebar')) {
                document.querySelectorAll('.sidebar nav ul li.clicked').forEach(function (li) {
                    li.classList.remove('clicked', 'open');
                });
                document.querySelectorAll('.sidebar nav ul li.disable-hover').forEach(function (li) {
                    li.classList.remove('disable-hover');
                });
            }
        });
    });
</script>


