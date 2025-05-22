<?php
// manage_roles.php
// This script allows administrators to view and manage user role assignments
session_start();
require_once('../../../../../config/ims-tmdd.php');

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

// Initialize RBAC & enforce "View"
$rbac = new RBACService($pdo, $_SESSION['user_id']);
$rbac->requirePrivilege('User Management', 'Modify');

// Process form submission for role updates
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
    try {
        $userId = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);
        $deptId = filter_var($_POST['dept_id'], FILTER_VALIDATE_INT);
        $roleId = filter_var($_POST['role_id'], FILTER_VALIDATE_INT);
        
        if ($userId && $deptId && $roleId !== false) {
            // Update the role assignment
            $stmt = $pdo->prepare("UPDATE user_department_roles SET role_id = ? WHERE user_id = ? AND department_id = ?");
            $stmt->execute([$roleId, $userId, $deptId]);
            
            $message = "Role assignment updated successfully!";
            $messageType = "success";
        } else {
            $message = "Invalid input data.";
            $messageType = "error";
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "error";
    }
}

// Get all users
$usersStmt = $pdo->query("SELECT id, username FROM users WHERE is_disabled = 0 ORDER BY username");
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

// Get all departments
$deptsStmt = $pdo->query("SELECT id, department_name FROM departments WHERE is_disabled = 0 ORDER BY department_name");
$departments = $deptsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get all roles
$rolesStmt = $pdo->query("SELECT id, role_name FROM roles WHERE is_disabled = 0 ORDER BY role_name");
$roles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);

// Get current assignments for the selected user
$selectedUserId = filter_var($_GET['user_id'] ?? 0, FILTER_VALIDATE_INT);
$assignments = [];

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

include '../../general/header.php';
include '../../general/sidebar.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>src/view/styles/css/user_module.css">
    <title>Manage User Role Assignments</title>
    <style>
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