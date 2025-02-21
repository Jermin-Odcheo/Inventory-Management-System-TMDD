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

    <!-- If you haven't already, link your main CSS, or place the .tree CSS below in that file -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>src/view/styles/css/sidebar.css">

    <h3>Menu</h3>
    <nav>
        <ul>
            <li>
                <a href="<?php echo BASE_URL; ?>src/view/php/clients/admins/dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>

            <!-- Example: Only show certain links for specific roles -->
            <?php if ($role === 'Super Admin' || $role === 'Admin'): ?>
                <!-- Admin-specific links here -->
            <?php endif; ?>

            <!-- Logs Dropdown -->
            <li class="dropdown-item">
                <button class="dropdown-toggle" aria-expanded="false">
                    <i class="fas fa-history"></i> Logs
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </button>
                <!-- Add .tree and aria-expanded to the <ul> -->
                <ul class="dropdown tree" aria-expanded="false">
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

            <!-- User Management Dropdown -->
            <li class="dropdown-item">
                <button class="dropdown-toggle" aria-expanded="false">
                    <i class="fa-solid fa-user"></i> User Management
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </button>
                <ul class="dropdown tree" aria-expanded="false">
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
                </ul>
            </li>
            <!-- Equipment Management Dropdown -->
            <li class="dropdown-item">
                <button class="dropdown-toggle" aria-expanded="false">
                    <i class="fa-solid fa-wrench"></i> Equipment Management
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </button>
                <ul class="dropdown tree" aria-expanded="false">
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
                            Equipment Status for PMS
                        </a>
                    </li>
                </ul>
            </li>
            <!-- Equipment Transaction Dropdown -->
            <li class="dropdown-item">
                <button class="dropdown-toggle" aria-expanded="false">
                    <i class="fa-solid fa-arrow-right-arrow-left"></i>Equipment Transaction
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </button>
                <ul class="dropdown tree" aria-expanded="false">
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
            </li>
        </ul>
        </li>


        </ul>
    </nav>
</div>

<!-- Include a small script to toggle aria-expanded on click -->
<script>
    // Query all toggles
    document.querySelectorAll('.dropdown-toggle').forEach((btn) => {
        btn.addEventListener('click', () => {
            const currentState = btn.getAttribute('aria-expanded') === 'true';
            // Flip the button's aria-expanded
            btn.setAttribute('aria-expanded', !currentState);

            // The next sibling should be the corresponding <ul class='dropdown tree'>
            const dropdownMenu = btn.nextElementSibling;
            if (dropdownMenu && dropdownMenu.classList.contains('dropdown')) {
                // Flip the <ul>'s aria-expanded as well
                dropdownMenu.setAttribute('aria-expanded', !currentState);
            }
        });
    });
</script>