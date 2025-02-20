<?php
session_start();
include '../../../../config/ims-tmdd.php'; // Include your database connection

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user details (roles & departments)
$query = "SELECT u.id, u.username, 
                 GROUP_CONCAT(r.role_name) AS roles, 
                 GROUP_CONCAT(d.department_name) AS departments 
          FROM users u
          LEFT JOIN user_roles ur ON u.id = ur.user_id
          LEFT JOIN roles r ON ur.role_id = r.id 
          LEFT JOIN user_departments ud ON u.id = ud.user_id
          LEFT JOIN departments d ON ud.department_id = d.id
          WHERE u.id = ?
          GROUP BY u.id";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="styles.css"> <!-- Add Bootstrap or custom styles -->
    <style>
        body { font-family: Arial, sans-serif; }
        .container { display: flex; margin: 20px; }
        aside { width: 25%; background: #f4f4f4; padding: 15px; border-radius: 5px; }
        main { width: 75%; padding: 15px; }
        ul { list-style-type: none; padding: 0; }
        ul li { padding: 5px; background: #ddd; margin: 5px 0; border-radius: 3px; }
        nav { display: flex; justify-content: space-between; padding: 15px; background: #333; color: #fff; }
        a { color: #fff; text-decoration: none; }
    </style>
</head>
<body>

    <nav>
        <h2>Welcome, <?php echo htmlspecialchars($user['username']); ?>!</h2>
        <a href="../../../../config/logout.php">Logout</a>
    </nav>

    <div class="container">
        <?php include '../general/sidebar.php'; ?> <!-- Sidebar included here -->

        <main>
            <h2>Dashboard</h2>
            <p><strong>Your Assigned Roles:</strong> <?php echo htmlspecialchars($user['roles'] ?? 'N/A'); ?></p>
            <p><strong>Your Departments:</strong> <?php echo htmlspecialchars($user['departments'] ?? 'N/A'); ?></p>

            <h3>Department-Specific Information</h3>
            <p>Content based on department access can be displayed here.</p>
        </main>
    </div>

</body>
</html>
