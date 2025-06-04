<?php
/**
 * @file manage_roles.php
 * @brief Allows administrators to view and manage user role assignments within departments.
 *
 * This script provides an interface for selecting a user and then viewing and updating
 * their assigned roles across different departments. It integrates with an RBAC service
 * for privilege enforcement and handles form submissions for role updates.
 */

session_start(); // Start the PHP session.
require_once('../../../../../config/ims-tmdd.php'); // Include the database connection file, providing the $pdo object.
require_once('../../../../../control/RBACService.php'); // Include the RBACService class.

// Check if user is logged in and has admin privileges
/**
 * Ensures the user is logged in. If not, redirects to the index page and exits.
 */
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

// Initialize RBAC & enforce "View"
/**
 * @var RBACService $rbac Initializes the RBACService with the PDO object and current user ID.
 * Enforces 'Modify' privilege for 'User Management' to access this page.
 */
$rbac = new RBACService($pdo, $_SESSION['user_id']);
$rbac->requirePrivilege('User Management', 'Modify'); // Requires 'Modify' privilege to manage roles.

// Process form submission for role updates
/**
 * @var string $message Stores success or error messages to be displayed to the user.
 * @var string $messageType Stores the type of message ('success' or 'error') for styling.
 */
$message = '';
$messageType = '';

/**
 * Checks if the request method is POST and if the 'update_role' button was submitted.
 * Processes the role update form submission.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
    try {
        /**
         * @var int|false $userId The ID of the user whose role is being updated, filtered as an integer.
         * @var int|false $deptId The ID of the department for which the role is being updated, filtered as an integer.
         * @var int|false $roleId The new role ID to assign, filtered as an integer.
         */
        $userId = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);
        $deptId = filter_var($_POST['dept_id'], FILTER_VALIDATE_INT);
        $roleId = filter_var($_POST['role_id'], FILTER_VALIDATE_INT);

        /**
         * Validates the input data. If all inputs are valid, proceeds with the database update.
         */
        if ($userId && $deptId && $roleId !== false) {
            // Update the role assignment
            /**
             * Prepares and executes a SQL statement to update the `role_id` in the `user_department_roles` table
             * for a specific user and department.
             *
             * @var PDOStatement $stmt The prepared SQL statement object.
             */
            $stmt = $pdo->prepare("UPDATE user_department_roles SET role_id = ? WHERE user_id = ? AND department_id = ?");
            $stmt->execute([$roleId, $userId, $deptId]);

            $message = "Role assignment updated successfully!";
            $messageType = "success";
        } else {
            $message = "Invalid input data.";
            $messageType = "error";
        }
    } catch (Exception $e) {
        /**
         * Catches any exceptions during the update process and sets an error message.
         */
        $message = "Error: " . $e->getMessage();
        $messageType = "error";
    }
}

// Get all users
/**
 * Fetches all active users (id, username) from the `users` table, ordered by username.
 *
 * @var PDOStatement $usersStmt The PDOStatement object for fetching users.
 * @var array $users An associative array containing all fetched users.
 */
$usersStmt = $pdo->query("SELECT id, username FROM users WHERE is_disabled = 0 ORDER BY username");
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

// Get all departments
/**
 * Fetches all active departments (id, department_name) from the `departments` table, ordered by department name.
 *
 * @var PDOStatement $deptsStmt The PDOStatement object for fetching departments.
 * @var array $departments An associative array containing all fetched departments.
 */
$deptsStmt = $pdo->query("SELECT id, department_name FROM departments WHERE is_disabled = 0 ORDER BY department_name");
$departments = $deptsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get all roles
/**
 * Fetches all active roles (id, role_name) from the `roles` table, ordered by role name.
 *
 * @var PDOStatement $rolesStmt The PDOStatement object for fetching roles.
 * @var array $roles An associative array containing all fetched roles.
 */
$rolesStmt = $pdo->query("SELECT id, role_name FROM roles WHERE is_disabled = 0 ORDER BY role_name");
$roles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);

// Get current assignments for the selected user
/**
 * @var int|false $selectedUserId The ID of the user selected from the dropdown, filtered as an integer.
 * Defaults to 0 if not set or invalid.
 * @var array $assignments An array to store the role assignments for the selected user.
 */
$selectedUserId = filter_var($_GET['user_id'] ?? 0, FILTER_VALIDATE_INT);
$assignments = [];

/**
 * If a user is selected, fetches their role assignments across departments.
 * Joins `user_department_roles` with `users`, `departments`, and `roles` tables
 * to get comprehensive assignment details.
 */
if ($selectedUserId) {
    $assignmentsStmt = $pdo->prepare("
        SELECT udr.user_id, udr.department_id, udr.role_id,
               u.username, d.department_name, r.role_name
        FROM user_department_roles udr
        JOIN users u ON udr.user_id = u.id
        JOIN departments d ON udr.department_id = d.id
        LEFT JOIN roles r ON udr.role_id = r.id
        WHERE udr.user_id = ?
        ORDER BY d.department_name
    ");
    $assignmentsStmt->execute([$selectedUserId]);
    $assignments = $assignmentsStmt->fetchAll(PDO::FETCH_ASSOC);
}

include '../../general/header.php'; // Include the general header HTML.
include '../../general/sidebar.php'; // Include the general sidebar HTML.
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>src/view/styles/css/user_module.css">
    <title>Manage User Role Assignments</title>
    <style>
        /* CSS styles for layout, messages, form elements, and table styling. */
        .container {
            padding: 20px;
        }
        .message {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        select, input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .btn {
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
    </style>
</head>
<body>
    <div class="main-content container-fluid">
        <header>
            <h1>MANAGE USER ROLE ASSIGNMENTS</h1>
        </header>

        <div class="container">
            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form method="GET" action="">
                <div class="form-group">
                    <label for="user_id">Select User:</label>
                    <select name="user_id" id="user_id" onchange="this.form.submit()">
                        <option value="">-- Select User --</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo $selectedUserId == $user['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>

            <?php if ($selectedUserId && !empty($assignments)): ?>
                <h2>Role Assignments for <?php echo htmlspecialchars($assignments[0]['username']); ?></h2>
                <table>
                    <thead>
                        <tr>
                            <th>Department</th>
                            <th>Current Role</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assignments as $assignment): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($assignment['department_name']); ?></td>
                                <td><?php echo htmlspecialchars($assignment['role_name'] ?? 'No Role (ID: ' . $assignment['role_id'] . ')'); ?></td>
                                <td>
                                    <form method="POST" action="" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $assignment['user_id']; ?>">
                                        <input type="hidden" name="dept_id" value="<?php echo $assignment['department_id']; ?>">
                                        <select name="role_id">
                                            <option value="0" <?php echo $assignment['role_id'] == 0 ? 'selected' : ''; ?>>No Role (0)</option>
                                            <?php foreach ($roles as $role): ?>
                                                <option value="<?php echo $role['id']; ?>" <?php echo $assignment['role_id'] == $role['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($role['role_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" name="update_role" class="btn btn-primary">Update</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php elseif ($selectedUserId): ?>
                <p>No role assignments found for this user.</p>
            <?php endif; ?>

            <div style="margin-top: 20px;">
                <a href="user_management.php" class="btn btn-primary">Back to User Management</a>
                <a href="fix_role_id.php" class="btn btn-primary">Fix Database</a>
            </div>
        </div>
    </div>

    <?php include '../../general/footer.php'; ?>
</body>
</html>
