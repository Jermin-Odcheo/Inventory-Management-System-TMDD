<?php
session_start();
require '../../../../config/ims-tmdd.php';
// If not logged in, redirect to the LOGIN PAGE
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "public/index.php");
    exit();
}

include '../general/header.php';
include '../general/sidebar.php';
include '../general/footer.php';

$role = $_SESSION['role'];
$email = $_SESSION['email']; // Assuming you stored email in session

// Define page title dynamically based on role
$dashboardTitle = "Dashboard"; // Default title
switch (strtolower(trim($role))) { // Normalize role to avoid case issues
    case 'super admin':
        $dashboardTitle = "Super Admin Dashboard";
        break;
    case 'tmdd-dev':
        $dashboardTitle = "TMDD-Dev";
        break;
    case 'super user':
        $dashboardTitle = "Super User Dashboard";
        break;
    case 'regular user':
        $dashboardTitle = "Regular User Dashboard";
        break;
    default:
        $dashboardTitle = "User Dashboard"; // Fallback
}

function getUserDetails($pdo, $userId) {
    // Get Roles
    $roleQuery = $pdo->prepare("
        SELECT DISTINCT r.role_name
        FROM roles r
        JOIN user_roles ur ON r.id = ur.role_id
        WHERE ur.user_id = ?
    ");

    // Get Modules and Privileges for testing purposes
    $modulePrivQuery = $pdo->prepare("
        SELECT 
            m.module_name,
            p.priv_name
        FROM role_module_privileges rmp
        JOIN modules m ON rmp.module_id = m.id
        JOIN privileges p ON rmp.privilege_id = p.id
        JOIN user_roles ur ON rmp.role_id = ur.role_id
        WHERE ur.user_id = ?
        ORDER BY m.module_name, p.priv_name
    ");

    $roleQuery->execute([$userId]);
    $modulePrivQuery->execute([$userId]);

    $roles = $roleQuery->fetchAll(PDO::FETCH_COLUMN);
    $modulePrivileges = $modulePrivQuery->fetchAll(PDO::FETCH_ASSOC);

    // Organize modules and their privileges
    $organizedModules = [];
    foreach ($modulePrivileges as $item) {
        if (!isset($organizedModules[$item['module_name']])) {
            $organizedModules[$item['module_name']] = [];
        }
        $organizedModules[$item['module_name']][] = $item['priv_name'];
    }

    return [
        'roles' => $roles,
        'modulePrivileges' => $organizedModules
    ];
}

$selectedDeptId = isset($_POST['DepartmentID']) ? $_POST['DepartmentID'] : '';

try {
    // RETRIEVE ALL DEPARTMENT IDs FOR THE USER
    $stmt = $pdo->prepare("SELECT department_id FROM user_departments WHERE User_ID = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $departmentIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($departmentIds)) {
        // RETRIEVE FULL DEPARTMENT INFO
        $placeholders = implode(',', array_fill(0, count($departmentIds), '?'));
        $stmt = $pdo->prepare("SELECT * FROM departments WHERE id IN ($placeholders)");
        $stmt->execute($departmentIds);
        $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $departments = []; // No departments found
    }
} catch (PDOException $e) {
    die("Error retrieving departments: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $dashboardTitle; ?></title>
    <link rel="stylesheet" href="../../../styles/css/dashboard.css">
    <style>
        body {
            background-color: #f4f7fc;
            color: #333;
        }
        
        .main-content {
            background-color: #f4f7fc;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background-color: #fff;
        }

        th, td {
            border: 1px solid #e0e0e0;
            padding: 10px;
            text-align: left;
        }
        
        h1 {
            color: #2c3e50;
        }
        
        .detail-card, .module-section {
            background-color: #fff;
            border: 1px solid #e0e0e0;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .privilege-list li {
            color: #333;
        }
        
        .dashboard-container {
            background-color: #f4f7fc;
        }
        
        section {
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border: 1px solid #e0e0e0;
        }
        
        select.form-control {
            background-color: #fff;
            border: 1px solid #e0e0e0;
        }
    </style>
</head>
<body>
<div class="main-content">
    <header>
        <h1>Welcome to the <?php echo $dashboardTitle; ?></h1>
        <p>Hello, <?php echo htmlspecialchars($email); ?>!</p>
    </header>

    <div class="dashboard-container">
        <!-- Role-Based Dashboard Content -->
        <?php if (strtolower(trim($role)) === 'super admin'): ?>
            <section>
                <h3>Super Admin Panel</h3>
                <p>Audit Trail, Roles &amp; Permissions, User Accounts, Equipment Modules, etc.</p>
            </section>
        <?php elseif (strtolower(trim($role)) === 'tmdd-dev'): ?>
            <section class="tmdd-dev-panel">
                <h3>TMDD-Dev Panel</h3>
                <?php $userDetails = getUserDetails($pdo, $_SESSION['user_id']); ?>
                <div class="user-details-container">
                    <div class="detail-card">
                        <h4>Your Roles</h4>
                        <ul class="detail-list">
                            <?php foreach ($userDetails['roles'] as $role): ?>
                                <li><?php echo htmlspecialchars($role); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="detail-card module-privileges">
                        <h4>Your Module Privileges</h4>
                        <?php if (!empty($userDetails['modulePrivileges'])): ?>
                            <?php foreach ($userDetails['modulePrivileges'] as $module => $privileges): ?>
                                <div class="module-section">
                                    <h5><?php echo htmlspecialchars($module); ?></h5>
                                    <ul class="privilege-list">
                                        <?php foreach ($privileges as $privilege): ?>
                                            <li><?php echo htmlspecialchars($privilege); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No module privileges assigned.</p>
                        <?php endif; ?>
                    </div>
                </div>
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

        <!-- Department Selection -->
        <form method="POST">
            <div class="mb-3">
                <select class="form-control" name="DepartmentID" id="departmentSelect" onchange="this.form.submit()">
                    <option value="" disabled selected>Select a department</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= $dept['id'] ?>" <?= ($selectedDeptId == $dept['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($dept['abbreviation'] . ' - ' . $dept['department_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <?php if ($selectedDeptId): ?>
            <section>
                <h2>Details for Selected Department</h2>
                <table>
                    <tr>
                        <th>Department ID</th>
                        <th>Department Acronym</th>
                        <th>Department Name</th>
                    </tr>
                    <?php foreach ($departments as $dept): ?>
                        <?php if ($dept['id'] == $selectedDeptId): ?>
                            <tr>
                                <td><?= htmlspecialchars($dept['id']) ?></td>
                                <td><?= htmlspecialchars($dept['abbreviation']) ?></td>
                                <td><?= htmlspecialchars($dept['department_name']) ?></td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </table>
            </section>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
