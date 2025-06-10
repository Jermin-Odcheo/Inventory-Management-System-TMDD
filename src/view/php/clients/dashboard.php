<?php
/**
 * Dashboard Module
 *
 * This file provides the main dashboard functionality for the system. It displays key metrics, recent activities, and system status information. The module handles data aggregation, user-specific views, and real-time updates to provide a comprehensive overview of the system's state.
 *
 * @package    InventoryManagementSystem
 * @subpackage Clients
 * @author     TMDD Interns 25'
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
        JOIN modules m ON rmp.module_id = m.id  /* FIX: Added missing JOIN keyword here */
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

/**
 * @var array $notifications
 * @brief Stores notifications for the user.
 *
 * This array contains the notifications relevant to the logged-in user.
 */
$notifications = getNotifications($pdo, $_SESSION['user_id']);


// --- PHP Data Fetching for Charts and Summary ---
/**
 * @brief Fetches monthly summary data for various transaction types and total counts.
 * @param PDO $pdo The PDO database connection object.
 * @return array An associative array containing monthly data for charge invoices, purchase orders, receiving reports, recently ordered items, and total counts for all three transaction types.
 */
function getMonthlySummaryData($pdo) {
    $data = [
        'charge_invoices_monthly' => [],
        'purchase_orders_monthly' => [],
        'receiving_reports_monthly' => [],
        'recently_ordered_items' => [], // Changed from top_item_specifications
        'total_orders_count' => 0, // All-time total purchase orders
        'total_charge_invoices_count' => 0, // All-time total charge invoices
        'total_receiving_reports_count' => 0 // All-time total receiving reports
    ];

    try {
        // Monthly Charge Invoices (last 12 months)
        $stmtCI = $pdo->prepare("
            SELECT
                DATE_FORMAT(date_of_purchase, '%Y-%m') AS month,
                COUNT(id) AS count
            FROM charge_invoice
            WHERE is_disabled = 0
              AND date_of_purchase IS NOT NULL
              AND date_of_purchase >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            GROUP BY month
            ORDER BY month ASC
        ");
        $stmtCI->execute();
        $data['charge_invoices_monthly'] = $stmtCI->fetchAll(PDO::FETCH_ASSOC);

        // Monthly Purchase Orders & Units (last 12 months)
        $stmtPO = $pdo->prepare("
            SELECT
                DATE_FORMAT(date_of_order, '%Y-%m') AS month,
                COUNT(id) AS count,
                SUM(no_of_units) AS total_units
            FROM purchase_order
            WHERE is_disabled = 0
              AND date_of_order IS NOT NULL
              AND date_of_order >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            GROUP BY month
            ORDER BY month ASC
        ");
        $stmtPO->execute();
        $data['purchase_orders_monthly'] = $stmtPO->fetchAll(PDO::FETCH_ASSOC);

        // Monthly Receiving Reports (last 12 months)
        $stmtRR = $pdo->prepare("
            SELECT
                DATE_FORMAT(date_created, '%Y-%m') AS month,
                COUNT(id) AS count
            FROM receive_report
            WHERE is_disabled = 0
              AND date_created IS NOT NULL
              AND date_created >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            GROUP BY month
            ORDER BY month ASC
        ");
        $stmtRR->execute();
        $data['receiving_reports_monthly'] = $stmtRR->fetchAll(PDO::FETCH_ASSOC);

        // Recently Ordered Items (last 10 orders) - Changed from Top Item Specifications
        $stmtRecentOrders = $pdo->prepare("
            SELECT
                po_no,
                item_specifications,
                no_of_units,
                date_of_order
            FROM purchase_order
            WHERE is_disabled = 0
            ORDER BY date_of_order DESC, id DESC
            LIMIT 10
        ");
        $stmtRecentOrders->execute();
        $data['recently_ordered_items'] = $stmtRecentOrders->fetchAll(PDO::FETCH_ASSOC);


        // Total Orders Count (All-time)
        $stmtTotalOrders = $pdo->prepare("
            SELECT COUNT(id) AS total_count
            FROM purchase_order
            WHERE is_disabled = 0
        ");
        $stmtTotalOrders->execute();
        $totalOrdersResult = $stmtTotalOrders->fetch(PDO::FETCH_ASSOC);
        $data['total_orders_count'] = $totalOrdersResult['total_count'];

        // Total Charge Invoices Count (All-time)
        $stmtTotalCI = $pdo->prepare("
            SELECT COUNT(id) AS total_count
            FROM charge_invoice
            WHERE is_disabled = 0
        ");
        $stmtTotalCI->execute();
        $totalCIResult = $stmtTotalCI->fetch(PDO::FETCH_ASSOC);
        $data['total_charge_invoices_count'] = $totalCIResult['total_count'];

        // Total Receiving Reports Count (All-time)
        $stmtTotalRR = $pdo->prepare("
            SELECT COUNT(id) AS total_count
            FROM receive_report
            WHERE is_disabled = 0
        ");
        $stmtTotalRR->execute();
        $totalRRResult = $stmtTotalRR->fetch(PDO::FETCH_ASSOC);
        $data['total_receiving_reports_count'] = $totalRRResult['total_count'];

    } catch (PDOException $e) {
        error_log("Error fetching monthly summary data: " . $e->getMessage());
        // Return empty data arrays on error to prevent JavaScript issues
    }
    return $data;
}

$dashboardData = getMonthlySummaryData($pdo);

// Encode data as JSON for JavaScript
echo '<script>';
// Using JSON_HEX_TAG and JSON_HEX_AMP to prevent potential XSS and malformed HTML issues when embedding JSON in <script> tags
echo 'const dashboardChartData = ' . json_encode($dashboardData, JSON_HEX_TAG | JSON_HEX_AMP) . ';';
echo '</script>';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $dashboardTitle; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>src/view/styles/css/dashboard.css">
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>
    <!-- Tailwind CSS for utility classes for the new list items -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Existing CSS styles */
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

        /* Module Activity Group Styles (Removed - No longer needed) */
        /*
        .module-activity-group {
            margin-bottom: 30px;
            background: #fff;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            max-height: 500px;
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
            flex-shrink: 0;
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
            overflow-y: auto;
            max-height: calc(500px - 80px);
            scrollbar-width: thin;
            scrollbar-color: #3498db #f0f0f0;
        }

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
            padding-right: 10px;
        }

        .activity-item:last-child {
            padding-bottom: 0;
        }

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
        */

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

            /*
            .activity-meta {
                flex-direction: column;
                gap: 8px;
            }

            .module-activity-group {
                max-height: 400px;
            }

            .activity-timeline {
                max-height: calc(400px - 80px);
            */
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

        /* Activity Logs Section (Removed - Styling commented out) */
        /*
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
        */

        .no-data {
            text-align: center;
            padding: 20px;
            color: #7f8c8d;
            background: #f8f9fa;
            border-radius: 10px;
            margin: 10px 0;
        }

        /* New styles for charts */
        .charts-section {
            margin-top: 40px;
        }

        .charts-section h2 {
            margin-bottom: 20px;
            color: #2c3e50;
            font-size: 1.8em;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chart-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            height: 400px; 
            display: flex; 
            flex-direction: column; /* Changed to column for title positioning */
            justify-content: flex-start; /* Align content to top */
            align-items: stretch; /* Stretch content to fill container */
        }
        
        .chart-container canvas {
            max-height: 100%; 
            width: 100% !important; 
            height: 100% !important; 
            flex-grow: 1; /* Allow canvas to take available space */
        }

        .chart-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .chart-container h3 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #2c3e50; /* Changed title color to match section titles */
            font-size: 1.4em; /* Increased font size for chart titles */
            font-weight: 600; /* Made titles bolder */
            border-bottom: 2px solid #e0e0e0; /* More prominent border */
            padding-bottom: 10px;
            text-align: center; /* Centered chart title */
        }
        
        .chart-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        @media (max-width: 1200px) {
            #activity-container {
                grid-template-columns: 1fr; 
                padding-bottom: 0; 
            }
        }

        @media (max-width: 768px) {
             .chart-grid {
                grid-template-columns: 1fr; 
            }
            .chart-container {
                height: 300px; 
            }
        }

        /* New styles for summary cards */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
            margin-bottom: 40px; /* Add some space below summary grid */
        }

        .summary-card {
            background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            position: relative;
            overflow: hidden;
            border-top: 6px solid; /* Placeholder for color bar */
        }

        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        /* Specific border colors for summary cards */
        .summary-card.total-orders {
            border-top-color: #3498db; /* Blue */
        }
        .summary-card.total-invoices {
            border-top-color: #2ecc71; /* Green */
        }
        .summary-card.total-reports {
            border-top-color: #f39c12; /* Orange */
        }

        .summary-card-icon {
            width: 60px;
            height: 60px;
            background: rgba(52, 152, 219, 0.1); /* Light background for icon */
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2em;
            color: #3498db; /* Icon color */
            margin-bottom: 15px;
            transition: transform 0.3s ease;
        }

        .summary-card.total-orders .summary-card-icon {
            background: rgba(52, 152, 219, 0.1);
            color: #3498db;
        }
        .summary-card.total-invoices .summary-card-icon {
            background: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
        }
        .summary-card.total-reports .summary-card-icon {
            background: rgba(243, 156, 18, 0.1);
            color: #f39c12;
        }


        .summary-card:hover .summary-card-icon {
            transform: scale(1.1);
        }

        .summary-card h3 {
            margin: 0 0 10px 0;
            font-size: 1.2em;
            color: #2c3e50;
            font-weight: 600;
        }

        .summary-card .value {
            font-size: 2.5em;
            font-weight: 700;
            color: #3498db;
            margin-bottom: 5px;
        }
        .summary-card.total-invoices .value {
            color: #2ecc71;
        }
        .summary-card.total-reports .value {
            color: #f39c12;
        }

        .summary-card .description {
            font-size: 0.9em;
            color: #7f8c8d;
        }

        @media (max-width: 768px) {
            .summary-grid {
                grid-template-columns: 1fr;
            }
        }

        /* New styles for recently placed orders list */
        .recent-order-item {
            background-color: #f8f9fa;
            padding: 12px 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.95em;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border-left: 4px solid #3498db; /* Subtle blue highlight */
        }
        .recent-order-item:hover {
            transform: translateX(5px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .recent-order-item:last-child {
            margin-bottom: 0;
        }
        .recent-order-item .font-semibold {
            color: #2c3e50;
        }
        .recent-order-item .text-blue-700 {
            color: #3498db; /* Match overall theme */
        }
        .recent-order-item .text-gray-600 {
            color: #7f8c8d;
        }
        .recent-order-item .text-gray-500 {
            color: #555;
            font-weight: 500;
        }
        .recent-order-item .text-xs {
            font-size: 0.8em;
            color: #999;
        }

    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // The module privileges toggle button and content are no longer needed
            // as the entire section is being removed.
            const toggleBtn = document.querySelector('.module-privileges-toggle');
            const content = document.querySelector('.module-privileges-content');
            const icon = document.querySelector('.toggle-icon');

            if (toggleBtn && content && icon) {
                toggleBtn.addEventListener('click', function() {
                    content.classList.toggle('active');
                    icon.style.transform = content.classList.contains('active') ? 'rotate(180deg)' : 'rotate(0)';
                });
            }

            // --- Chart.js Initialization ---
            const monthlyTransactionsCanvas = document.getElementById('monthlyTransactionsChart');
            const totalUnitsCanvas = document.getElementById('totalUnitsChart');
            // Changed from itemSpecsCanvas to recentlyPlacedOrdersList
            const recentlyPlacedOrdersList = document.getElementById('recentlyPlacedOrdersList');


            if (typeof dashboardChartData !== 'undefined' && Object.keys(dashboardChartData).length > 0) {

                // Generate labels for the last 12 months (e.g., "Jan 2024")
                const monthLabels = [];
                const currentDate = new Date();
                currentDate.setDate(1); // Set to 1st to avoid issues with months having fewer days

                for (let i = 11; i >= 0; i--) {
                    const d = new Date(currentDate.getFullYear(), currentDate.getMonth() - i, 1);
                    monthLabels.push(d.toLocaleString('en-US', { month: 'short', year: 'numeric' }));
                }

                // Helper function to map data to labels for a given month
                const mapDataToLabels = (data, field, labelKey = 'month') => {
                    const mapped = {};
                    // Ensure data is an array before attempting to iterate
                    if (!Array.isArray(data)) {
                        console.warn(`mapDataToLabels: Expected array for data, but received ${typeof data}. Returning array of zeros.`);
                        return Array(monthLabels.length).fill(0);
                    }
                    data.forEach(item => {
                        // Ensure item[labelKey] is a string before splitting
                        if (typeof item[labelKey] === 'string') {
                            const [year, monthNum] = item[labelKey].split('-').map(Number);
                            const date = new Date(year, monthNum - 1, 1);
                            const label = date.toLocaleString('en-US', { month: 'short', year: 'numeric' });
                            mapped[label] = item[field];
                        } else {
                            console.warn(`mapDataToLabels: Invalid labelKey value encountered: ${item[labelKey]}`);
                        }
                    });
                    return monthLabels.map(label => mapped[label] || 0);
                };

                // Data for Monthly Transaction Overview
                const ciCounts = mapDataToLabels(dashboardChartData.charge_invoices_monthly, 'count');
                const poCounts = mapDataToLabels(dashboardChartData.purchase_orders_monthly, 'count');
                const rrCounts = mapDataToLabels(dashboardChartData.receiving_reports_monthly, 'count');

                // Data for Total Units Ordered by Month
                const totalUnits = mapDataToLabels(dashboardChartData.purchase_orders_monthly, 'total_units');

                // Data for Recently Ordered Items (reverted from Top Item Specifications)
                const recentlyOrderedItems = (dashboardChartData.recently_ordered_items && Array.isArray(dashboardChartData.recently_ordered_items))
                                            ? dashboardChartData.recently_ordered_items : [];


                // Common Chart Options for better aesthetics (only applies to actual charts)
                const commonChartOptions = {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: {
                        duration: 1000, // General animation time
                        easing: 'easeInOutQuart' // Smooth animation
                    },
                    plugins: {
                        legend: {
                            display: true, // Ensure legends are displayed by default unless explicitly hidden
                            position: 'bottom', // Default legend position
                            labels: {
                                font: {
                                    size: 13,
                                    family: "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif"
                                },
                                color: '#555', // Darker legend text
                                usePointStyle: true, // Use point style for all legends
                                padding: 20 // Padding between legend items
                            },
                        },
                        tooltip: {
                            enabled: true, // Enable tooltips
                            backgroundColor: 'rgba(39, 55, 70, 0.95)', // Slightly darker, more opaque
                            titleFont: {
                                size: 15,
                                family: "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif",
                                weight: 'bold'
                            },
                            bodyFont: {
                                size: 13,
                                family: "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif"
                            },
                            padding: 12,
                            cornerRadius: 6,
                            displayColors: true,
                            bodySpacing: 4, // Spacing between body lines
                            titleSpacing: 6, // Spacing between title and body
                        },
                        title: {
                            display: false, // Set to false here, as titles are in h3 tags within HTML
                            font: {
                                size: 18,
                                weight: 'bold',
                                family: "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif"
                            },
                            padding: {
                                top: 10,
                                bottom: 20
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false, // Hide vertical grid lines
                                drawOnChartArea: false, 
                            },
                            ticks: {
                                font: {
                                    size: 11,
                                    family: "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif",
                                    color: '#666'
                                },
                                autoSkip: true, // Automatically skip labels to prevent overlap
                                maxRotation: 45, // Max rotation for x-axis labels
                                minRotation: 0
                            },
                            title: {
                                display: true,
                                font: {
                                    size: 13,
                                    weight: 'bold',
                                    family: "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif"
                                },
                                color: '#444',
                                padding: { top: 15 }
                            }
                        },
                        y: {
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)', // Even lighter horizontal grid lines
                                drawBorder: false, // Do not draw axis line
                            },
                            ticks: {
                                font: {
                                    size: 11,
                                    family: "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif",
                                    color: '#666'
                                },
                                callback: function(value) {
                                    if (Number.isInteger(value)) {
                                        return value; // Ensure integer values for counts/units
                                    }
                                    return null;
                                }
                            },
                            title: {
                                display: true,
                                font: {
                                    size: 13,
                                    weight: 'bold',
                                    family: "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif"
                                },
                                color: '#444',
                                padding: { bottom: 15 }
                            }
                        }
                    }
                };


                // --- Chart 1: Monthly Transaction Overview (Bar Chart) ---
                try {
                    if (monthlyTransactionsCanvas) {
                        new Chart(monthlyTransactionsCanvas.getContext('2d'), {
                            type: 'bar',
                            data: {
                                labels: monthLabels,
                                datasets: [
                                    {
                                        label: 'Charge Invoices',
                                        data: ciCounts,
                                        backgroundColor: function(context) {
                                            const chart = context.chart;
                                            const {ctx, chartArea} = chart;
                                            if (!chartArea) return;
                                            const gradient = ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top);
                                            gradient.addColorStop(0, 'rgba(52, 152, 219, 0.7)');
                                            gradient.addColorStop(1, 'rgba(52, 152, 219, 1)');
                                            return gradient;
                                        },
                                        hoverBackgroundColor: 'rgba(52, 152, 219, 1)',
                                        borderColor: 'rgba(52, 152, 219, 1)',
                                        borderWidth: 1,
                                        borderRadius: 6,
                                        barPercentage: 0.8,
                                        categoryPercentage: 0.7
                                    },
                                    {
                                        label: 'Purchase Orders',
                                        data: poCounts,
                                        backgroundColor: function(context) {
                                            const chart = context.chart;
                                            const {ctx, chartArea} = chart;
                                            if (!chartArea) return;
                                            const gradient = ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top);
                                            gradient.addColorStop(0, 'rgba(46, 204, 113, 0.7)');
                                            gradient.addColorStop(1, 'rgba(46, 204, 113, 1)');
                                            return gradient;
                                        },
                                        hoverBackgroundColor: 'rgba(46, 204, 113, 1)',
                                        borderColor: 'rgba(46, 204, 113, 1)',
                                        borderWidth: 1,
                                        borderRadius: 6,
                                        barPercentage: 0.8,
                                        categoryPercentage: 0.7
                                    },
                                    {
                                        label: 'Receiving Reports',
                                        data: rrCounts,
                                        backgroundColor: function(context) {
                                            const chart = context.chart;
                                            const {ctx, chartArea} = chart;
                                            if (!chartArea) return;
                                            const gradient = ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top);
                                            gradient.addColorStop(0, 'rgba(241, 196, 15, 0.7)');
                                            gradient.addColorStop(1, 'rgba(241, 196, 15, 1)');
                                            return gradient;
                                        },
                                        hoverBackgroundColor: 'rgba(241, 196, 15, 1)',
                                        borderColor: 'rgba(241, 196, 15, 1)',
                                        borderWidth: 1,
                                        borderRadius: 6,
                                        barPercentage: 0.8,
                                        categoryPercentage: 0.7
                                    }
                                ]
                            },
                            options: {
                                ...commonChartOptions,
                                plugins: {
                                    ...commonChartOptions.plugins,
                                    legend: {
                                        ...commonChartOptions.plugins.legend,
                                        position: 'top',
                                    }
                                },
                                scales: {
                                    x: {
                                        ...commonChartOptions.scales.x,
                                        stacked: false,
                                        title: {
                                            ...commonChartOptions.scales.x.title,
                                            text: 'Month'
                                        }
                                    },
                                    y: {
                                        ...commonChartOptions.scales.y,
                                        beginAtZero: true,
                                        title: {
                                            ...commonChartOptions.scales.y.title,
                                            text: 'Number of Transactions'
                                        },
                                        ticks: {
                                            ...commonChartOptions.scales.y.ticks,
                                            stepSize: 1 
                                        }
                                    }
                                }
                            }
                        });
                    } else {
                        const chartContainer = monthlyTransactionsCanvas ? monthlyTransactionsCanvas.closest('.chart-container') : null;
                        if (chartContainer) {
                            chartContainer.innerHTML = '<div class="no-data"><i class="fas fa-exclamation-triangle"></i> No monthly transaction data available.</div>';
                            chartContainer.style.height = '150px'; 
                            chartContainer.style.display = 'flex';
                            chartContainer.style.justifyContent = 'center';
                            chartContainer.style.alignItems = 'center';
                        }
                    }
                } catch (error) {
                    console.error("Error initializing Monthly Transactions Chart:", error);
                }


                // --- Chart 2: Total Units Ordered by Month (Line Chart) ---
                try {
                    if (totalUnitsCanvas) {
                        new Chart(totalUnitsCanvas.getContext('2d'), {
                            type: 'line',
                            data: {
                                labels: monthLabels,
                                datasets: [
                                    {
                                        label: 'Total Units Ordered',
                                        data: totalUnits,
                                        backgroundColor: function(context) {
                                            const chart = context.chart;
                                            const {ctx, chartArea} = chart;
                                            if (!chartArea) return;
                                            const gradient = ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top);
                                            gradient.addColorStop(0, 'rgba(155, 89, 182, 0.4)'); // Lighter gradient start
                                            gradient.addColorStop(1, 'rgba(155, 89, 182, 0.7)'); // Darker gradient end
                                            return gradient;
                                        },
                                        hoverBackgroundColor: 'rgba(155, 89, 182, 0.9)',
                                        borderColor: 'rgba(155, 89, 182, 1)',
                                        borderWidth: 3,
                                        fill: true,
                                        tension: 0.4,
                                        pointRadius: 5,
                                        pointBackgroundColor: 'rgba(155, 89, 182, 1)',
                                        pointBorderColor: '#ffffff',
                                        pointBorderWidth: 2,
                                        pointHoverRadius: 7
                                    }
                                ]
                            },
                            options: {
                                ...commonChartOptions,
                                plugins: {
                                    ...commonChartOptions.plugins,
                                    legend: {
                                        ...commonChartOptions.plugins.legend,
                                        position: 'top',
                                    }
                                },
                                scales: {
                                    x: {
                                        ...commonChartOptions.scales.x,
                                        title: {
                                            ...commonChartOptions.scales.x.title,
                                            text: 'Month'
                                        }
                                    },
                                    y: {
                                        ...commonChartOptions.scales.y,
                                        beginAtZero: true,
                                        title: {
                                            ...commonChartOptions.scales.y.title,
                                            text: 'Total Units'
                                        },
                                        ticks: {
                                            ...commonChartOptions.scales.y.ticks,
                                            precision: 0 
                                        }
                                    }
                                }
                            }
                        });
                    } else {
                        const chartContainer = totalUnitsCanvas ? totalUnitsCanvas.closest('.chart-container') : null;
                        if (chartContainer) {
                            chartContainer.innerHTML = '<div class="no-data"><i class="fas fa-exclamation-triangle"></i> No units ordered data available.</div>';
                            chartContainer.style.height = '150px'; 
                            chartContainer.style.display = 'flex';
                            chartContainer.style.justifyContent = 'center';
                            chartContainer.style.alignItems = 'center';
                        }
                    }
                } catch (error) {
                    console.error("Error initializing Total Units Chart:", error);
                }


                // --- List: Recently Placed Orders ---
                if (recentlyPlacedOrdersList) {
                    if (recentlyOrderedItems.length > 0) {
                        recentlyPlacedOrdersList.innerHTML = ''; // Clear "no data" message
                        // Added h-full and overflow-y-auto to the container in HTML
                        // Removed setting height/display/justify/align from JS here
                        recentlyOrderedItems.forEach(order => {
                            const orderItem = document.createElement('div');
                            // Using Tailwind classes for styling
                            orderItem.className = 'recent-order-item bg-gray-50 p-3 mb-2 rounded-lg shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between text-sm transition-all duration-200 ease-in-out border-l-4 border-blue-500 hover:shadow-md hover:translate-x-1';
                            orderItem.innerHTML = `
                                <div class="flex-grow mb-1 sm:mb-0">
                                    <span class="font-semibold text-blue-800">${order.item_specifications}</span> 
                                    <span class="text-gray-600 text-xs sm:text-sm">(PO: ${order.po_no})</span>
                                </div>
                                <div class="text-right flex flex-col items-end sm:items-start sm:ml-4">
                                    <span class="text-gray-700 font-medium">${order.no_of_units} units</span>
                                    <span class="text-xs text-gray-500">${new Date(order.date_of_order).toLocaleDateString()}</span>
                                </div>
                            `;
                            recentlyPlacedOrdersList.appendChild(orderItem);
                        });
                    } else {
                        recentlyPlacedOrdersList.innerHTML = '<div class="no-data"><i class="fas fa-box-open"></i> No recent orders to display.</div>';
                        recentlyPlacedOrdersList.style.height = '150px'; // Set height for no data message
                        recentlyPlacedOrdersList.style.display = 'flex';
                        recentlyPlacedOrdersList.style.justifyContent = 'center';
                        recentlyPlacedOrdersList.style.alignItems = 'center';
                    }
                }


            } else {
                const chartContainers = document.querySelectorAll('.charts-section .chart-container');
                chartContainers.forEach(container => {
                    // Check if the container actually has a canvas, otherwise it might be the list container
                    if (container.querySelector('canvas')) {
                        container.innerHTML = '<div class="no-data"><i class="fas fa-exclamation-triangle"></i> No chart data available.</div>';
                    } else { // This else block will now correctly target the list container too
                        container.innerHTML = '<div class="no-data"><i class="fas fa-box-open"></i> No data available to display.</div>';
                    }
                    container.style.height = '150px'; 
                    container.style.display = 'flex'; 
                    container.style.justifyContent = 'center';
                    container.style.alignItems = 'center';
                });
            }
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

        <!-- Summary Statistics Section -->
        <section class="summary-stats-section">
            <h2>Overall Summary <i class="fas fa-info-circle"></i></h2>
            <div class="summary-grid">
                <div class="summary-card total-orders">
                    <div class="summary-card-icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <h3>Total Purchase Orders</h3>
                    <div class="value"><?php echo htmlspecialchars($dashboardData['total_orders_count']); ?></div>
                    <div class="description">All time, non-disabled orders</div>
                </div>
                <!-- You can add more summary cards here, e.g., for total charge invoices, total receiving reports -->
                <div class="summary-card total-invoices">
                    <div class="summary-card-icon">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <h3>Total Charge Invoices</h3>
                    <div class="value"><?php echo htmlspecialchars($dashboardData['total_charge_invoices_count']); ?></div>
                    <div class="description">All time, non-disabled invoices</div>
                </div>
                 <div class="summary-card total-reports">
                    <div class="summary-card-icon">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <h3>Total Receiving Reports</h3>
                    <div class="value"><?php echo htmlspecialchars($dashboardData['total_receiving_reports_count']); ?></div>
                    <div class="description">All time, non-disabled reports</div>
                </div>
            </div>
        </section>

        <!-- Charts Section -->
        <section class="charts-section">
            <h2>Equipment Transaction Insights <i class="fas fa-chart-bar"></i></h2>
            <div class="chart-grid">
                <div class="chart-container">
                    <h3>Monthly Transaction Volume</h3>
                    <canvas id="monthlyTransactionsChart"></canvas>
                </div>
                <div class="chart-container">
                    <h3>Units Ordered Trends</h3>
                    <canvas id="totalUnitsChart"></canvas>
                </div>
                 <div class="chart-container">
                    <h3>Recently Placed Orders</h3>
                    <div id="recentlyPlacedOrdersList" class="h-full overflow-y-auto">
                        <!-- Content loaded by JavaScript -->
                        <div class="no-data"><i class="fas fa-box-open"></i> No recent orders to display.</div>
                    </div>
                </div>
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
    </div>
</div>
</body>
<?php include '../general/footer.php'; ?>
</html>
