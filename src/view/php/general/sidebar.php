<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$role = isset($_SESSION['role']) ? $_SESSION['role'] : '';
?>
<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <button class="toggle-button" id="toggle-button" aria-label="Toggle Sidebar">
        <i class="fas fa-bars"></i>
    </button>
    <link rel="stylesheet" href="../../../styles/css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <h2>Inventory Management System</h2>
    <h3>Menu</h3>
    <nav>
        <ul>
            <li><a href="/src/view/php/clients/admins/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>

            <!-- Show User Management only for Super Admins & Admins -->
            <?php if ($role === 'Super Admin' || $role === 'Admin'): ?>
                <li><a href="/src/view/php/clients/admins/audit_log.php"><i class="fas fa-history"></i> Audit Logs</a></li>
                <li>
                    <a href="#" class="dropdown-toggle"><i class="fa-solid fa-user"></i> User Management</a>
                    <ul class="dropdown">
                        <li><a href="../../modules/user_manager/user_management.php">Manage Accounts</a></li>
                        <li><a href="../../modules/user_manager/user_management.php">Manage Roles</a></li>
                        <li><a href="#">Manage Privileges</a></li>
                    </ul>
                </li>
            <?php endif; ?>

            <!-- Equipment Management -->
            <li>
                <a href="#" class="dropdown-toggle"><i class="fa-solid fa-wrench"></i> Equipment Management</a>
                <ul class="dropdown">
                    <li><a href="purchase_order.php">Purchase Order</a></li>
                    <li><a href="charge_invoice.php">Charge Invoice</a></li>
                    <li><a href="receiving_report.php">Receiving Report</a></li>
                    <li><a href="equipment_details.php">Equipment Details</a></li>
                    <li><a href="equipment_location.php">Equipment Location</a></li>
                    <li><a href="equipment_status.php">Equipment Status</a></li>
                </ul>
            </li>

            <!-- Settings & Logout -->
            <li><a href="settings.php"><i class="fas fa-cogs"></i> Settings</a></li>
            <li><a href="../../general/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </nav>
</div>

<script>
    const toggleButton = document.getElementById('toggle-button');
    const sidebar = document.getElementById('sidebar');

    toggleButton.addEventListener('click', () => {
        sidebar.classList.toggle('active');
        // Change the icon based on the sidebar state
        const icon = sidebar.classList.contains('active') ? 'fa-times' : 'fa-bars';
        toggleButton.querySelector('i').className = `fas ${icon}`;
    });
</script>