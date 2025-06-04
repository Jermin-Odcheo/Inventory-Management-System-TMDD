<?php

/**
 * Fixes null role ID values in the user_department_roles table of the Inventory Management System.
 * 
 * This utility script updates all null role_id values to 0 in the user_department_roles table to ensure
 * data consistency. It also displays the current role assignments for review. The script requires the user
 * to be logged in and includes basic styling for the output page.
 */
// fix_role_id.php
// This script updates null role_id values to 0 in the user_department_roles table
// and shows current role assignments

require_once('../../../../../config/ims-tmdd.php');
session_start();

/**
 * Performs authentication check to ensure the user is logged in before proceeding with database updates.
 */
if (!isset($_SESSION['user_id'])) {
    die("Not authorized");
}

// Add some basic styling
echo '<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    table { border-collapse: collapse; width: 100%; margin-top: 20px; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    tr:nth-child(even) { background-color: #f9f9f9; }
    .success { color: green; }
    .back-link { margin-top: 20px; display: block; }
</style>';

echo "<h1>Database Fix Utility</h1>";

try {
    /**
     * Updates all null role_id values to 0 in the user_department_roles table.
     */
    $stmt = $pdo->prepare("UPDATE user_department_roles SET role_id = 0 WHERE role_id IS NULL");
    $result = $stmt->execute();
    
    $count = $stmt->rowCount();
    
    echo "<p class='success'>Fixed $count records with null role_id values.</p>";
    echo "<p>All null role_id values have been updated to 0.</p>";
    
    /**
     * Displays the current role assignments for all users after the update.
     */
    echo "<h2>Current Role Assignments</h2>";
    
    $query = "
        SELECT u.username, d.department_name, r.role_name, udr.role_id
        FROM user_department_roles udr
        JOIN users u ON udr.user_id = u.id
        JOIN departments d ON udr.department_id = d.id
        LEFT JOIN roles r ON udr.role_id = r.id
        ORDER BY u.username, d.department_name
    ";
    
    $stmt = $pdo->query($query);
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($assignments) > 0) {
        echo "<table>";
        echo "<tr><th>Username</th><th>Department</th><th>Role</th><th>Role ID</th></tr>";
        
        foreach ($assignments as $assignment) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($assignment['username']) . "</td>";
            echo "<td>" . htmlspecialchars($assignment['department_name']) . "</td>";
            echo "<td>" . htmlspecialchars($assignment['role_name'] ?? 'No Role') . "</td>";
            echo "<td>" . htmlspecialchars($assignment['role_id']) . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p>No role assignments found.</p>";
    }
    
    echo "<a href='user_management.php' class='back-link'>Return to User Management</a>";
    
} catch (PDOException $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?> 