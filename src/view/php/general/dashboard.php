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
    <link rel="stylesheet" href="../../styles/css/dashboard.css">
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" integrity="sha512-Fo3rlrZj/k7ujTTX3DJ2m59tJ3qDzB6c0nZZjPjIejM7pwl0KiPxH9M5jJ8yQ0kX1HVhbiq3uOqIqJ6KJ5s2Og==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>

<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="dashboard-container">
            <h2>Welcome to the Admin Dashboard</h2>
            <!-- Add dashboard content here -->
        </div>
    </div>
</body>

</html>