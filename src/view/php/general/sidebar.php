<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$role = isset($_SESSION['role']) ? $_SESSION['role'] : '';
?>
<!-- Sidebar -->
<div class="sidebar">
    <link rel="stylesheet" href="../../../styles/css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <h2><?php echo htmlspecialchars($role); ?> Panel</h2>
    <h2>Menu</h2>
    <nav>
        <ul>
<<<<<<< Updated upstream
            <li><a href="/src/view/php/clients/admins/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>

            <!-- Show User Management only for Super Admins & Admins -->
            <?php if ($role === 'Super Admin' || $role === 'Admin'): ?>
                <li><a href="/src/view/php/clients/admins/audit_log.php"><i class="fas fa-history"></i> Audit Logs</a></li>
=======
            <li><a href="../../clients/admins/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <!-- Show User Management only for Super Users & Administrators -->
            <?php if ($role === 'Super User'): ?>
                <a href="../../../modules/user_manager/user_management.php">
                    <i class="fa-solid fa-user"></i> User Management</a>
>>>>>>> Stashed changes
                <li>
                    <a href="#" class="dropdown-toggle"><i class="fa-solid fa-user"></i> User Management</a>
                    <ul class="dropdown">
                        <li><a href="../../modules/user_manager/user_management.php">Manage Accounts</a></li>
                        <li><a href="../../modules/user_manager/manage_roles.php">Manage Roles</a></li>
                        <li><a href="#">Manage Privileges</a></li>
                    </ul>
                </li>
            <?php endif; ?>

<<<<<<< Updated upstream
            <!-- Show User Management only for Super Admins & Admins -->
            <?php if ($role === 'Super User' || $role === 'User'): ?>
                <li><a href="#"><i class="fas fa-history"></i> Audit Logs</a></li>
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
=======
            <!-- Show Manage Roles only for s -->
            <?php if ($role === 'Super Admin'): ?>
                <li><a href="../../modules/user_manager/user_management.php"><i class="fas fa-cogs"></i> Manage Roles</a></li>
            <?php endif; ?>

            <!-- Registrar & regsol users get a unique option -->
            <?php if ($role === 'Registrar' || $role === 'regsol'): ?>
                <li><a href="../../modules/registrar/records.php"><i class="fas fa-folder-open"></i> Records Management</a></li>
            <?php endif; ?>

            <!-- Secretary Role -->
            <?php if ($role === 'Sec'): ?>
                <li><a href="../../modules/sec/schedule.php"><i class="fas fa-calendar-alt"></i> Schedule Management</a></li>
            <?php endif; ?>

            <!-- Show Settings only for Super Users & Administrators -->
            <?php if ($role === 'Super User' || $role === 'Administrator'): ?>
                <li><a href="settings.php"><i class="fas fa-cogs"></i> Settings</a></li>
            <?php endif; ?>

            <!-- Logout (Visible to Everyone) -->
>>>>>>> Stashed changes
            <li><a href="../../general/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </nav>
</div>