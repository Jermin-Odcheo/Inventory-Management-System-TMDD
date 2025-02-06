<?php
session_start();
require '../../../../../config/ims-tmdd.php';

// Redirect to login if the user is not logged in
if (!isset($_SESSION['role'])) {
    header("Location: ../../../../../public/index.php");
    exit();
}

// Debug: Log the current role (remove in production)
error_log("Current Session Role: " . $_SESSION['role']);

$role = $_SESSION['role'];
$email = $_SESSION['email']; // Assuming you stored email in session

// Define page title dynamically based on role
$dashboardTitle = "Dashboard"; // Default title
switch (strtolower(trim($role))) { // Normalize role to avoid case issues
    case 'super admin':
        $dashboardTitle = "Super Admin Dashboard";
        break;
    case 'administrator':
        $dashboardTitle = "Administrator Dashboard";
        break;
    case 'super user':
        $dashboardTitle = "Super User Dashboard";
        break;
    case 'regular':
        $dashboardTitle = "Regular User Dashboard";
        break;
    default:
        $dashboardTitle = "User Dashboard"; // Fallback
}

// No need to retrieve all users here if you're going to do it via fetch_user_status.php
// But if you want a fallback server-side render, you can keep it.
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title><?php echo $dashboardTitle; ?></title>
    <link rel="stylesheet" href="../../../styles/css/dashboard.css">

<body>
    <!-- Include Sidebar -->
    <?php include '../../general/sidebar.php'; ?>

    <div class="main-content">
        <div class="dashboard-container">
            <h2>Welcome to the <?php echo $dashboardTitle; ?></h2>
            <p>Hello, <?php echo htmlspecialchars($email); ?>!</p>

            <!-- Role-Based Dashboard Content -->
            <?php if (strtolower(trim($role)) === 'super admin'): ?>
                <section>
                    <h3>Super Admin Panel</h3>
                    <p>Audit Trail, Roles &amp; Permissions, User Accounts, Equipment Modules, etc.</p>
                </section>
            <?php elseif (strtolower(trim($role)) === 'administrator'): ?>
                <section>
                    <h3>Administrator Panel</h3>
                    <p>Audit Trail (Dept), Roles &amp; Permissions (Dept), etc.</p>
                </section>
            <?php elseif (strtolower(trim($role)) === 'super user'): ?>
                <section>
                    <h3>Super User Panel</h3>
                    <p>Roles &amp; Permissions (Group), User Accounts (Group), etc.</p>
                </section>
            <?php elseif (strtolower(trim($role)) === 'regular user'): ?>
                <section>
                    <h3>Regular User Panel</h3>
                    <p>User Accounts (Own), Equipment Modules (Dept), etc.</p>
                </section>
            <?php else: ?>
                <section>
                    <h3>Standard User Panel</h3>
                    <p>You have limited access to the system.</p>
                </section>
            <?php endif; ?>
            <p>You have limited access to the system.</p>
            <hr>

        </div>
    </div>
</body>

</html>