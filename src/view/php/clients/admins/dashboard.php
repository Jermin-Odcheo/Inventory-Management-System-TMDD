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
    <link rel="stylesheet" href="../../../styles/css/sidebar.css">
    <link rel="stylesheet" href="../../../styles/css/dashboard.css">
    <style>
        /* Additional styling for the online/offline table */
        table {
            background-color: #242424;
            width: 100%;
            border-collapse: collapse;
        }

        table th {
            background-color: #0d6efd;
            color: #fff;
            padding: 8px;
        }

        table td {
            padding: 8px;
            color: #ffffff;
            border-bottom: 1px solid #343a40;
        }

        .status-online {
            color: #00ff00;
            font-weight: bold;
        }

        .status-offline {
            color: #ff3333;
            font-weight: bold;
        }

        .dashboard-container hr {
            margin: 20px 0;
            border-color: #343a40;
        }
    </style>
    <!-- JavaScript to update last_active periodically (the Heartbeat) -->
    <script>
        function sendHeartbeat() {
            fetch('heartbeat.php', {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(data => {
                    console.log('Heartbeat response:', data);
                })
                .catch(error => console.error('Error in heartbeat:', error));
        }

        // Send a heartbeat every 30 seconds
        setInterval(sendHeartbeat, 30000);
        sendHeartbeat(); // Also call once immediately


        // Fetch the user status every 5 seconds
        setInterval(fetchUserStatus, 5000);
        // Or fetch once when the page loads
        fetchUserStatus();

        function fetchUserStatus() {
            fetch('fetch_user_status.php')
                .then(response => response.json())
                .then(users => {
                    const tbody = document.getElementById('userTableBody');
                    tbody.innerHTML = ''; // Clear existing rows

                    users.forEach(user => {
                        const row = document.createElement('tr');

                        // Use the isOnline property from the server
                        const statusHTML = user.isOnline ?
                            '<span style="color: lime; font-weight: bold;">Online</span>' :
                            '<span style="color: red; font-weight: bold;">Offline</span>';

                        row.innerHTML = `
                    <td>${user.User_ID}</td>
                    <td>${user.First_Name} ${user.Last_Name}</td>
                    <td>${user.Email}</td>
                    <td>${user.Department}</td>
                    <td>${statusHTML}</td>
                `;
                        tbody.appendChild(row);
                    });
                })
                .catch(error => console.error('Error fetching user status:', error));
        }
    </script>
</head>

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

            <hr>

            <!-- Online/Offline User Listing -->
            <section>
                <h3>User Online Status</h3>
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Department</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="userTableBody">
                        <!-- Dynamically loaded via JavaScript -->
                        <!-- (You can keep the original PHP rows for fallback if JS is disabled) -->
                    </tbody>
                </table>
            </section>
        </div>
    </div>
</body>

</html>