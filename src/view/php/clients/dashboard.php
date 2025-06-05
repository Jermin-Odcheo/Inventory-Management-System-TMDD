<?php
/**
 * @file dashboard.php
 * @brief Main dashboard for client interface.
 *
 * This script serves as the primary dashboard for clients, providing an overview
 * of key metrics, recent activities, and access to various system functionalities.
 */
session_start();
require '../../../../config/ims-tmdd.php';
// If not logged in, redirect to the LOGIN PAGE
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit();
}

include '../general/header.php';
include '../general/sidebar.php';

// Add dashboard class to body
echo '<script>document.body.classList.add("dashboard");</script>';

/**
 * @var string $role
 * @brief Stores the user's role from the session.
 *
 * This variable holds the role of the currently logged-in user.
 */
$role = $_SESSION['role'];

/**
 * @var string $email
 * @brief Stores the user's email from the session.
 *
 * This variable holds the email address of the currently logged-in user.
 */
$email = $_SESSION['email']; // Assuming you stored email in session

// Define page title dynamically based on role
/**
 * @var string $dashboardTitle
 * @brief Stores the title of the dashboard page.
 *
 * This variable sets the default title for the dashboard.
 */
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

/**
 * @var string $selectedDeptId
 * @brief Stores the selected department ID from POST data.
 *
 * This variable holds the department ID selected by the user, if any.
 */
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
        /**
         * @var array $departments
         * @brief Stores the list of departments associated with the user.
         *
         * This array contains full department information retrieved from the database.
         */
        $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        /**
         * @var array $departments
         * @brief Stores an empty array if no departments are found.
         *
         * This array is empty if no departments are associated with the user.
         */
        $departments = []; // No departments found
    }
} catch (PDOException $e) {
    die("Error retrieving departments: " . $e->getMessage());
}

// Get recent activities based on user's access rights
/**
 * @brief Retrieves recent activities based on user's access rights.
 * @param \PDO $pdo Database connection object.
 * @param int $userId The ID of the user whose activities are to be retrieved.
 * @param int $limit The maximum number of activities to retrieve (default is 5).
 * @return array Returns an array of grouped activities by module.
 */
function getRecentActivities($pdo, $userId, $limit = 5) {
    try {
        // First, let's check if the user has any module permissions
        $checkPermissions = $pdo->prepare("
            SELECT DISTINCT m.id, m.module_name
            FROM modules m
            JOIN role_module_privileges rmp ON m.id = rmp.module_id
            JOIN user_department_roles udr ON rmp.role_id = udr.role_id
            WHERE udr.user_id = ?
        ");
        $checkPermissions->execute([$userId]);
        $userModules = $checkPermissions->fetchAll(PDO::FETCH_ASSOC);

        if (empty($userModules)) {
            return [];
        }

        // Get activities for the modules the user has access to
        $moduleNames = array_column($userModules, 'module_name');
        $placeholders = str_repeat('?,', count($moduleNames) - 1) . '?';
        
        $query = $pdo->prepare("
            SELECT 
                al.TrackID as id,
                al.Action,
                al.Details as description,
                al.Date_Time as created_at,
                u.email as user_email,
                al.Module as module_name
            FROM audit_log al
            JOIN users u ON al.UserID = u.id
            WHERE al.Module IN ($placeholders)
            ORDER BY al.Module, al.Date_Time DESC
            LIMIT ?
        ");
        
        $params = array_merge($moduleNames, [$limit]);
        $query->execute($params);
        $activities = $query->fetchAll(PDO::FETCH_ASSOC);

        // Group activities by module
        $groupedActivities = [];
        foreach ($activities as $activity) {
            $moduleName = $activity['module_name'];
            if (!isset($groupedActivities[$moduleName])) {
                $groupedActivities[$moduleName] = [];
            }
            $groupedActivities[$moduleName][] = $activity;
        }

        return $groupedActivities;
    } catch (PDOException $e) {
        error_log("Error in getRecentActivities: " . $e->getMessage());
        return [];
    }
}

// Get relevant notifications based on user's access rights
/**
 * @brief Retrieves notifications based on user's access rights.
 * @param \PDO $pdo Database connection object.
 * @param int $userId The ID of the user whose notifications are to be retrieved.
 * @param int $limit The maximum number of notifications to retrieve (default is 5).
 * @return array Returns an array of notifications.
 */
function getNotifications($pdo, $userId, $limit = 5) {
    try {
        $query = $pdo->prepare("
            SELECT 
                n.id,
                n.title,
                n.message,
                n.created_at,
                n.priority,
                m.module_name
            FROM notifications n
            JOIN modules m ON n.module_id = m.id
            JOIN role_module_privileges rmp ON m.id = rmp.module_id
            JOIN user_department_roles udr ON rmp.role_id = udr.role_id
            WHERE udr.user_id = ?
            AND n.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY n.priority DESC, n.created_at DESC
            LIMIT ?
        ");
        
        $query->execute([$userId, $limit]);
        return $query->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // If table doesn't exist or other error, return empty array
        return [];
    }
}

// Get the activities and notifications
/**
 * @var array $recentActivities
 * @brief Stores recent activities for the user.
 *
 * This array contains the recent activities grouped by module for the logged-in user.
 */
$recentActivities = getRecentActivities($pdo, $_SESSION['user_id']);

/**
 * @var array $notifications
 * @brief Stores notifications for the user.
 *
 * This array contains the notifications relevant to the logged-in user.
 */
$notifications = getNotifications($pdo, $_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $dashboardTitle; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>src/view/styles/css/dashboard.css">
    <style>
        body {
            background-color: #f4f7fc;
            color: #333;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .main-content {
            background-color: #f4f7fc;
            padding: 30px 50px;
            margin-top: 30px;
        }

        /* Welcome Section Styles */
        .welcome-section {
            background: linear-gradient(135deg, #3498db, #2c3e50);
            color: white;
            padding: 40px;
            border-radius: 20px;
            margin-bottom: 40px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }

        .welcome-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg width="20" height="20" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><circle cx="2" cy="2" r="1" fill="rgba(255,255,255,0.1)"/></svg>');
            opacity: 0.1;
        }

        .welcome-section h1 {
            font-size: 2.8em;
            margin: 0;
            font-weight: 700;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
            position: relative;
        }

        .welcome-section p {
            font-size: 1.4em;
            margin: 15px 0 0 0;
            opacity: 0.9;
            position: relative;
        }

        .welcome-section .user-avatar {
            position: absolute;
            right: 40px;
            top: 50%;
            transform: translateY(-50%);
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2em;
            color: white;
            border: 3px solid rgba(255,255,255,0.3);
        }

        /* Module Activity Group Styles */
        .module-activity-group {
            margin-bottom: 30px;
            background: #fff;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            max-height: 500px; /* Set maximum height */
            display: flex;
            flex-direction: column;
        }

        .module-activity-group:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.1);
        }

        .module-title {
            color: #2c3e50;
            font-size: 1.4em;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e0e0;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0; /* Prevent title from shrinking */
        }

        .module-title::before {
            content: '';
            display: inline-block;
            width: 8px;
            height: 8px;
            background: #3498db;
            border-radius: 50%;
        }

        .activity-timeline {
            position: relative;
            padding-left: 30px;
            overflow-y: auto; /* Enable vertical scrolling */
            max-height: calc(500px - 80px); /* Adjust for title height */
            scrollbar-width: thin; /* For Firefox */
            scrollbar-color: #3498db #f0f0f0; /* For Firefox */
        }

        /* Custom scrollbar for Webkit browsers */
        .activity-timeline::-webkit-scrollbar {
            width: 8px;
        }

        .activity-timeline::-webkit-scrollbar-track {
            background: #f0f0f0;
            border-radius: 4px;
        }

        .activity-timeline::-webkit-scrollbar-thumb {
            background: #3498db;
            border-radius: 4px;
        }

        .activity-timeline::-webkit-scrollbar-thumb:hover {
            background: #2980b9;
        }

        .activity-item {
            position: relative;
            padding-bottom: 20px;
            padding-right: 10px; /* Add padding for scrollbar */
        }

        .activity-item:last-child {
            padding-bottom: 0;
        }

        /* Add a fade effect at the bottom of scrollable content */
        .activity-timeline::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 30px;
            background: linear-gradient(to bottom, rgba(255,255,255,0), rgba(255,255,255,1));
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .activity-timeline:not(:hover)::after {
            opacity: 1;
        }

        /* Add scroll indicator when content is scrollable */
        .scroll-indicator {
            text-align: center;
            color: #7f8c8d;
            font-size: 0.9em;
            margin-top: 10px;
            display: none;
        }

        .module-activity-group.has-scroll .scroll-indicator {
            display: block;
        }

        .activity-icon {
            position: absolute;
            left: -30px;
            width: 24px;
            height: 24px;
            background: #3498db;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 4px rgba(52,152,219,0.3);
        }

        .activity-content {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: transform 0.2s ease;
        }

        .activity-content:hover {
            transform: translateX(5px);
        }

        .activity-description {
            margin: 0 0 12px 0;
            color: #2c3e50;
            line-height: 1.5;
            font-size: 1.05em;
        }

        .activity-description strong {
            color: #3498db;
            font-weight: 600;
        }

        .activity-meta {
            display: flex;
            gap: 20px;
            font-size: 0.95em;
            color: #7f8c8d;
        }

        .activity-meta span {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .activity-meta i {
            font-size: 1em;
        }

        .activity-user i {
            color: #3498db;
        }

        .activity-time i {
            color: #e67e22;
        }

        .no-data {
            text-align: center;
            padding: 30px;
            color: #7f8c8d;
            background: #f8f9fa;
            border-radius: 12px;
            font-size: 1.1em;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
            }

            .welcome-section {
                padding: 30px;
                text-align: center;
            }

            .welcome-section .user-avatar {
                position: relative;
                right: auto;
                top: auto;
                transform: none;
                margin: 20px auto 0;
            }

            .activity-meta {
                flex-direction: column;
                gap: 8px;
            }

            .module-activity-group {
                max-height: 400px; /* Smaller max height on mobile */
            }

            .activity-timeline {
                max-height: calc(400px - 80px);
            }
        }

        /* Add loading animation styles */
        .loading-spinner {
            display: none;
            text-align: center;
            padding: 20px;
        }

        .loading-spinner.active {
            display: block;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .refresh-time {
            text-align: right;
            font-size: 0.9em;
            color: #7f8c8d;
            margin-bottom: 10px;
        }

        /* Add these new styles */
        .user-info-card {
            background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
        }

        .user-info-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, #3498db, #2ecc71);
        }

        .user-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
        }

        .user-avatar-large {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #3498db, #2c3e50);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2em;
            box-shadow: 0 4px 10px rgba(52,152,219,0.3);
            border: 3px solid white;
        }

        .user-welcome {
            flex: 1;
        }

        .user-welcome h3 {
            margin: 0;
            color: #2c3e50;
            font-size: 1.4em;
            font-weight: 600;
        }

        .user-welcome p {
            margin: 5px 0 0;
            color: #7f8c8d;
            font-size: 1.1em;
        }

        .user-details {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-top: 20px;
        }

        .detail-item {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .detail-item:last-child {
            border-bottom: none;
        }

        .detail-icon {
            width: 40px;
            height: 40px;
            background: #f8f9fa;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: #3498db;
            font-size: 1.2em;
        }

        .detail-content {
            flex: 1;
        }

        .detail-label {
            color: #7f8c8d;
            font-size: 0.9em;
            margin-bottom: 3px;
        }

        .detail-value {
            color: #2c3e50;
            font-weight: 500;
        }

        .roles-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }

        .role-badge {
            background: #e8f4fd;
            color: #3498db;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.9em;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .role-badge i {
            font-size: 0.9em;
        }

        @media (max-width: 768px) {
            .user-header {
                flex-direction: column;
                text-align: center;
            }

            .user-avatar-large {
                margin: 0 auto;
            }
        }

        /* Add these new styles */
        .department-card {
            background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
        }

        .department-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, #e74c3c, #f39c12);
        }

        .department-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .department-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #e74c3c, #f39c12);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5em;
            box-shadow: 0 4px 10px rgba(231,76,60,0.3);
        }

        .department-title {
            color: #2c3e50;
            font-size: 1.3em;
            font-weight: 600;
            margin: 0;
        }

        .department-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .department-item {
            background: white;
            padding: 15px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 10px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .department-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .department-item i {
            color: #e74c3c;
            font-size: 1.2em;
        }

        .department-item span {
            color: #2c3e50;
            font-weight: 500;
        }

        /* Access Rights Styles */
        .access-rights-card {
            background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
        }

        .access-rights-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, #2ecc71, #3498db);
        }

        .module-privileges-toggle {
            background: white;
            border: none;
            padding: 20px;
            border-radius: 15px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .module-privileges-toggle:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .module-privileges-toggle h2 {
            margin: 0;
            font-size: 1.3em;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .module-privileges-toggle h2 i {
            color: #2ecc71;
        }

        .toggle-icon {
            transition: transform 0.3s ease;
            color: #7f8c8d;
        }

        .module-privileges-content {
            display: none;
            padding: 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .module-privileges-content.active {
            display: block;
            animation: slideDown 0.3s ease-out;
        }

        .module-section {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            transition: transform 0.2s ease;
        }

        .module-section:hover {
            transform: translateX(5px);
        }

        .module-section h3 {
            color: #2c3e50;
            font-size: 1.2em;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .module-section h3 i {
            color: #3498db;
        }

        .privilege-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
        }

        .privilege-item {
            background: white;
            padding: 10px 15px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.95em;
            color: #2c3e50;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .privilege-item i {
            color: #2ecc71;
            font-size: 0.9em;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            .department-list,
            .privilege-list {
                grid-template-columns: 1fr;
            }
        }

        /* Add animation for new activities */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .activity-item.new {
            animation: slideIn 0.5s ease-out;
        }

        .live-indicator {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: #e74c3c;
            font-size: 0.9em;
            margin-left: 10px;
        }

        .live-indicator::before {
            content: '';
            width: 8px;
            height: 8px;
            background: #e74c3c;
            border-radius: 50%;
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
                opacity: 1;
            }
            50% {
                transform: scale(1.2);
                opacity: 0.7;
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        /* Activity Logs Section */
        .activity-logs {
            margin-top: 30px;
        }

        .activity-logs h2 {
            margin-bottom: 20px;
            color: #2c3e50;
            font-size: 1.8em;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        #activity-container {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-top: 20px;
        }

        .module-activity-group {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            display: flex;
            flex-direction: column;
            max-height: 600px;
        }

        .module-activity-group:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .module-title {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            padding: 20px;
            margin: 0;
            font-size: 1.3em;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .module-title i {
            font-size: 1.2em;
        }

        .activity-timeline {
            padding: 20px;
            overflow-y: auto;
            flex-grow: 1;
            background: #f8f9fa;
        }

        .activity-item {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: transform 0.2s ease;
            position: relative;
            border-left: 4px solid #3498db;
        }

        .activity-item:hover {
            transform: translateX(5px);
        }

        .activity-item:last-child {
            margin-bottom: 0;
        }

        .activity-icon {
            position: absolute;
            left: -8px;
            top: 50%;
            transform: translateY(-50%);
            width: 16px;
            height: 16px;
            background: #3498db;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.8em;
        }

        .activity-content {
            padding-left: 15px;
        }

        .activity-description {
            margin: 0 0 10px 0;
            color: #2c3e50;
            line-height: 1.5;
        }

        .activity-description strong {
            color: #3498db;
            font-weight: 600;
        }

        .activity-meta {
            display: flex;
            gap: 15px;
            font-size: 0.9em;
            color: #7f8c8d;
        }

        .activity-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .activity-meta i {
            font-size: 1em;
        }

        .scroll-indicator {
            background: #f8f9fa;
            padding: 10px;
            text-align: center;
            color: #7f8c8d;
            font-size: 0.9em;
            border-top: 1px solid #e9ecef;
            display: none;
        }

        .module-activity-group.has-scroll .scroll-indicator {
            display: block;
        }

        .no-data {
            text-align: center;
            padding: 20px;
            color: #7f8c8d;
            background: #f8f9fa;
            border-radius: 10px;
            margin: 10px 0;
        }

        @media (max-width: 1200px) {
            #activity-container {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .activity-meta {
                flex-direction: column;
                gap: 5px;
            }

            .module-activity-group {
                max-height: 500px;
            }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleBtn = document.querySelector('.module-privileges-toggle');
            const content = document.querySelector('.module-privileges-content');
            const icon = document.querySelector('.toggle-icon');

            toggleBtn.addEventListener('click', function() {
                content.classList.toggle('active');
                icon.style.transform = content.classList.contains('active') ? 'rotate(180deg)' : 'rotate(0)';
            });

            // WebSocket connection
            const ws = new WebSocket('ws://localhost:8080');
            const activityGroups = {};

            ws.onopen = function() {
                console.log('Connected to activity stream');
                // Send user ID to server
                ws.send(JSON.stringify({
                    type: 'auth',
                    userId: <?php echo $_SESSION['user_id']; ?>
                }));
            };

            // Initial load of activities
            fetch('get_recent_activities.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.text();
                })
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const moduleGroups = doc.querySelectorAll('.module-activity-group');
                    
                    // Clear existing activities
                    const container = document.querySelector('#activity-container');
                    container.innerHTML = '';
                    
                    if (moduleGroups.length === 0) {
                        container.innerHTML = '<div class="no-data">No modules with track permissions found.</div>';
                        return;
                    }
                    
                    // Add each module group
                    moduleGroups.forEach(moduleGroup => {
                        const moduleName = moduleGroup.querySelector('.module-title').textContent.trim();
                        activityGroups[moduleName] = moduleGroup;
                        container.appendChild(moduleGroup);
                        
                        // Check if content is scrollable
                        const timeline = moduleGroup.querySelector('.activity-timeline');
                        if (timeline.scrollHeight > timeline.clientHeight) {
                            moduleGroup.classList.add('has-scroll');
                        }
                    });
                })
                .catch(error => {
                    console.error('Error loading initial activities:', error);
                    document.querySelector('#activity-container').innerHTML = 
                        '<div class="no-data">Error loading activities. Please try again later.</div>';
                });

            // WebSocket message handler
            ws.onmessage = function(e) {
                const activity = JSON.parse(e.data);
                const moduleName = activity.module_name;
                let moduleGroup = activityGroups[moduleName];

                if (!moduleGroup) {
                    // Create new module group if it doesn't exist
                    moduleGroup = document.createElement('div');
                    moduleGroup.className = 'module-activity-group';
                    moduleGroup.innerHTML = `
                        <h3 class="module-title">
                            <i class="fas fa-cube"></i>
                            ${moduleName}
                        </h3>
                        <div class="activity-timeline"></div>
                        <div class="scroll-indicator">
                            <i class="fas fa-chevron-down"></i> Scroll for more activities
                        </div>
                    `;
                    document.querySelector('#activity-container').appendChild(moduleGroup);
                    activityGroups[moduleName] = moduleGroup;
                }

                const timeline = moduleGroup.querySelector('.activity-timeline');
                const activityItem = document.createElement('div');
                activityItem.className = 'activity-item new';
                activityItem.innerHTML = `
                    <div class="activity-icon">
                        <i class="fas fa-circle"></i>
                    </div>
                    <div class="activity-content">
                        <p class="activity-description">
                            <strong>${activity.Action}:</strong>
                            ${activity.description}
                        </p>
                        <div class="activity-meta">
                            <span class="activity-user">
                                <i class="fas fa-user"></i> ${activity.user_email}
                            </span>
                            <span class="activity-time">
                                <i class="fas fa-clock"></i> ${new Date(activity.created_at).toLocaleString()}
                            </span>
                        </div>
                    </div>
                `;

                // Remove "no activities" message if it exists
                const noData = timeline.querySelector('.no-data');
                if (noData) {
                    noData.remove();
                }

                // Add new activity at the top
                timeline.insertBefore(activityItem, timeline.firstChild);

                // Remove old activities if more than 5
                const activities = timeline.querySelectorAll('.activity-item');
                if (activities.length > 5) {
                    activities[activities.length - 1].remove();
                }

                // Check if content is scrollable
                if (timeline.scrollHeight > timeline.clientHeight) {
                    moduleGroup.classList.add('has-scroll');
                }
            };
        });
    </script>
</head>
<body>
<div class="main-content">
    <div class="welcome-section">
        <h1>Welcome to the <?php echo $dashboardTitle; ?></h1>
        <p>Hello, <?php echo htmlspecialchars($email); ?>! 👋</p>
        <div class="user-avatar">
            <i class="fas fa-user"></i>
        </div>
    </div>

    <div class="dashboard-container">
        <?php
        // Get user details including roles and module privileges
        $userDetails = getUserDetails($pdo, $_SESSION['user_id']);
        ?>

        <!-- Activity Logs Section -->
        <section class="activity-logs">
            <h2>Recent Activities <span class="live-indicator">LIVE</span></h2>
            <div id="activity-container">
                <!-- Activities will be dynamically added here -->
            </div>
        </section>

        <!-- User Information Section -->
        <section class="user-info">
            <h2>User Information</h2>
            <div class="user-info-card">
                <div class="user-header">
                    <div class="user-avatar-large">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="user-welcome">
                        <h3><?php echo htmlspecialchars($email); ?></h3>
                        <p>Welcome back! 👋</p>
                    </div>
                </div>
                <div class="user-details">
                    <div class="detail-item">
                        <div class="detail-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="detail-content">
                            <div class="detail-label">Email Address</div>
                            <div class="detail-value"><?php echo htmlspecialchars($email); ?></div>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-icon">
                            <i class="fas fa-user-tag"></i>
                        </div>
                        <div class="detail-content">
                            <div class="detail-label">Your Roles</div>
                            <div class="roles-list">
                                <?php foreach ($userDetails['roles'] as $role): ?>
                                    <span class="role-badge">
                                        <i class="fas fa-shield-alt"></i>
                                        <?php echo htmlspecialchars($role); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Departments Section -->
        <section class="departments">
            <h2>Your Departments</h2>
            <div class="department-card">
                <div class="department-header">
                    <div class="department-icon">
                        <i class="fas fa-building"></i>
                    </div>
                    <h3 class="department-title">Department Overview</h3>
                </div>
                <?php if (!empty($departments)): ?>
                    <div class="department-list">
                        <?php foreach ($departments as $dept): ?>
                            <div class="department-item">
                                <i class="fas fa-building"></i>
                                <span><?php echo htmlspecialchars($dept['department_name']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-data">No departments assigned.</div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Module Privileges Section -->
        <section class="module-privileges">
            <button class="module-privileges-toggle">
                <h2><i class="fas fa-shield-alt"></i> Your Access Rights</h2>
                <span class="toggle-icon">▼</span>
            </button>
            <div class="module-privileges-content">
                <?php if (!empty($userDetails['modulePrivileges'])): ?>
                    <?php foreach ($userDetails['modulePrivileges'] as $module => $privileges): ?>
                        <div class="module-section">
                            <h3><i class="fas fa-cube"></i> <?php echo htmlspecialchars($module); ?></h3>
                            <div class="privilege-list">
                                <?php foreach ($privileges as $privilege): ?>
                                    <div class="privilege-item">
                                        <i class="fas fa-check-circle"></i>
                                        <?php echo htmlspecialchars($privilege); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-data">No module privileges assigned.</div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>
</body>
<?php include '../general/footer.php'; ?>
</html>
