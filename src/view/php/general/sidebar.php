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
            <li><a href="../../clients/admins/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <!-- Show User Management only for Super Users & Administrators -->
            <?php if ($role === 'Super User'): ?>
                <a href="../../../modules/user_manager/user_management.php">
                    <i class="fa-solid fa-user"></i> User Management</a>
                <li>
                    <a href="#" class="dropdown-toggle"><i class="fa-solid fa-user"></i> User Management</a>
                    <ul class="dropdown">
                        <li><a href="../../modules/user_manager/user_management.php">Manage Accounts</a></li>
                        <li><a href="../../modules/user_manager/manage_roles.php">Manage Roles</a></li>
                        <li><a href="#">Manage Privileges</a></li>
                    </ul>
                </li>
            <?php endif; ?>

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
            <li><a href="../../general/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </nav>
</div>