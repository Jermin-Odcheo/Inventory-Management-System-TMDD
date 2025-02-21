<?php
session_start();
include $_SERVER['DOCUMENT_ROOT'] . '/IMS-TMDD RABAC Tester/config/ims-tmdd.php';


// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user details (roles & departments)
$query = "
    SELECT 
        u.username, 
        GROUP_CONCAT(DISTINCT r.role_name) AS roles, 
        GROUP_CONCAT(DISTINCT d.department_name) AS departments 
    FROM users u
    LEFT JOIN user_roles ur ON u.id = ur.user_id
    LEFT JOIN roles r ON ur.role_id = r.id
    LEFT JOIN user_departments ud ON u.id = ud.user_id
    LEFT JOIN departments d ON ud.department_id = d.id
    WHERE u.id = ?
    GROUP BY u.id
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    die("User not found.");
}

$roles = explode(',', $user['roles'] ?? '');
$roles_str = implode(',', array_map('trim', $roles));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../style/style.css">
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-dark bg-dark">
        <?php include '../general/header.php'?>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 bg-light p-3">
                <?php include '../general/sidebar.php'; ?>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 p-4">
                <h2>Dashboard</h2>
                <p><strong>Your Assigned Roles:</strong> <?php echo htmlspecialchars($user['roles'] ?? 'N/A'); ?></p>
                <p><strong>Your Departments:</strong> <?php echo htmlspecialchars($user['departments'] ?? 'N/A'); ?></p>

                <h3>Department-Specific Information</h3>
                <p>Content based on department access can be displayed here.</p>
            </div>
        </div>
    </div>

</body>
</html>
