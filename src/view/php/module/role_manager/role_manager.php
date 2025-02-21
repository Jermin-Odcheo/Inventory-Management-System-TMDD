<?php
session_start();
include $_SERVER['DOCUMENT_ROOT'] . '/IMS-TMDD RABAC Tester/config/ims-tmdd.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Fetch users and their roles
$query = "
    SELECT u.id, u.username, GROUP_CONCAT(DISTINCT r.role_name) AS roles, 
           GROUP_CONCAT(DISTINCT d.department_name) AS departments 
    FROM users u
    LEFT JOIN user_roles ur ON u.id = ur.user_id
    LEFT JOIN roles r ON ur.role_id = r.id
    LEFT JOIN user_departments ud ON u.id = ud.user_id
    LEFT JOIN departments d ON ud.department_id = d.id
    GROUP BY u.id
";

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../style/style.css">
</head>

<body>

    <!-- Navbar -->
    <nav class="navbar navbar-dark bg-dark">
        <?php include '../../general/header.php'?>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 bg-light p-3">
                <?php include $_SERVER['DOCUMENT_ROOT'] . '/IMS-TMDD RABAC Tester/src/view/php/general/sidebar.php'; ?>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 p-4">
                <h2>Role Manager</h2>
                <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addUserModal">No Content</button>
                <p>Please modify Content to view</p>
            </div>
        </div>
    </div>
</body>

</html>