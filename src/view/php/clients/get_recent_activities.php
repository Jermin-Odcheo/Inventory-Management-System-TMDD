<?php
session_start();
require '../../../../config/ims-tmdd.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

try {
    // Get modules with track permissions for the user
    $moduleQuery = $pdo->prepare("
        SELECT DISTINCT m.module_name, m.id
        FROM modules m
        JOIN role_module_privileges rmp ON m.id = rmp.module_id
        JOIN privileges p ON rmp.privilege_id = p.id
        JOIN user_department_roles udr ON rmp.role_id = udr.role_id
        WHERE udr.user_id = ?
        AND p.priv_name = 'track'
        ORDER BY m.module_name
    ");
    $moduleQuery->execute([$_SESSION['user_id']]);
    $userModules = $moduleQuery->fetchAll(PDO::FETCH_ASSOC);

    if (empty($userModules)) {
        echo '<div class="no-data">No modules with track permissions found.</div>';
        exit;
    }

    // Get module names for the query
    $moduleNames = array_column($userModules, 'module_name');

    // Get activities for all modules the user has track permissions for
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
        AND al.Date_Time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY al.Module, al.Date_Time DESC
    ");
    
    $query->execute($moduleNames);
    $activities = $query->fetchAll(PDO::FETCH_ASSOC);

    // Initialize grouped activities with all modules, even if they have no activities
    $groupedActivities = [];
    foreach ($userModules as $module) {
        $groupedActivities[$module['module_name']] = [];
    }

    // Add activities to their respective modules
    foreach ($activities as $activity) {
        $moduleName = $activity['module_name'];
        if (isset($groupedActivities[$moduleName])) {
            $groupedActivities[$moduleName][] = $activity;
        }
    }

    // Output HTML for each module's activities
    foreach ($groupedActivities as $moduleName => $moduleActivities) {
        echo '<div class="module-activity-group">';
        echo '<h3 class="module-title"><i class="fas fa-cube"></i> ' . htmlspecialchars($moduleName) . '</h3>';
        echo '<div class="activity-timeline">';
        
        if (empty($moduleActivities)) {
            echo '<div class="no-data">No recent activities for this module</div>';
        } else {
            foreach ($moduleActivities as $activity) {
                echo '<div class="activity-item">';
                echo '<div class="activity-icon"><i class="fas fa-circle"></i></div>';
                echo '<div class="activity-content">';
                echo '<p class="activity-description">';
                echo '<strong>' . htmlspecialchars($activity['Action']) . ':</strong> ';
                echo htmlspecialchars($activity['description']);
                echo '</p>';
                echo '<div class="activity-meta">';
                echo '<span class="activity-user"><i class="fas fa-user"></i> ' . htmlspecialchars($activity['user_email']) . '</span>';
                echo '<span class="activity-time"><i class="fas fa-clock"></i> ' . date('M d, Y H:i', strtotime($activity['created_at'])) . '</span>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
            }
        }
        
        echo '</div>';
        echo '<div class="scroll-indicator"><i class="fas fa-chevron-down"></i> Scroll for more activities</div>';
        echo '</div>';
    }

} catch (PDOException $e) {
    error_log("Error in get_recent_activities: " . $e->getMessage());
    echo '<div class="no-data">Error loading activities. Please try again later.</div>';
}
?> 