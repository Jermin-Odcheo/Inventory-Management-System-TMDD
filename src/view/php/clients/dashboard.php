<?php
session_start();
require '../../../../config/ims-tmdd.php';
// If not logged in, redirect to the LOGIN PAGE
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit();
}

include '../general/header.php';
include '../general/sidebar.php';


$role = $_SESSION['role'];
$email = $_SESSION['email']; // Assuming you stored email in session

// Define page title dynamically based on role
$dashboardTitle = "Dashboard"; // Default title
function getUserDetails($pdo, $userId)
{
    // Get Roles
    $roleQuery = $pdo->prepare("
        SELECT DISTINCT r.role_name
        FROM roles r
        JOIN user_department_roles ur ON r.id = ur.role_id
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
        JOIN user_department_roles ur ON rmp.role_id = ur.role_id
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
    $stmt = $pdo->prepare("SELECT department_id FROM user_department_roles WHERE User_ID = ?");
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
    <!-- <link rel="stylesheet" href="<?php echo BASE_URL; ?>src/view/styles/css/dashboard.css">
    <style>
        body {
            background-color: #f4f7fc;
            color: #333;
        }

        .main-content {
            background-color: #f4f7fc;
            padding-left: 60px;
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
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
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
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            border: 1px solid #e0e0e0;
        }

        select.form-control {
            background-color: #fff;
            border: 1px solid #e0e0e0;
        }
    </style> -->
</head>
<body>
<div class="main-content">
    <header>
        <h1>Welcome to the <?php echo $dashboardTitle; ?></h1>
        <p>Hello, <?php echo htmlspecialchars($email); ?>!</p>
    </header>

    <div class="dashboard-container">
    </div>
</div>
</body>
<?php include '../general/footer.php'; ?>
</html>
