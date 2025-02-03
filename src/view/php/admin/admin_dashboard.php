<?php
session_start();

// Redirect to login if the user is not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require '../../../../config/ims-tmdd.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../../styles/css/sidebar.css">
    <link rel="stylesheet" href="../../styles/css/dashboard.css">
  </head>

<body>
    <?php include '../general/sidebar.php'; ?>
    <div class="main-content">
        <div class="dashboard-container">
            <h2>Welcome to the Admin Dashboard</h2>
            <!-- Add dashboard content here -->
        </div>
    </div>
</body>
    
</html>