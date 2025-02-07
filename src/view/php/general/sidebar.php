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
                <li>
                    <a href="#" class="dropdown-toggle"><i class="fa-solid fa-user"></i> User Management</a>
                    <ul class="dropdown">
                        <li><a href="../../modules/user_manager/user_management.php">Manage Accounts</a></li>
                        <li><a href="#">Manage Privileges</a></li>
                    </ul>
                </li>
            <?php endif; ?>


            <li><a href="../../general/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </nav>
</div>