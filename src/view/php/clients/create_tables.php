<?php
/**
 * Create Tables Module
 *
 * This file provides functionality for initializing and creating database tables required by the system. It handles table creation, schema definition, and initial data population. The module ensures proper database structure and data integrity during system setup.
 *
 * @package    InventoryManagementSystem
 * @subpackage Clients
 * @author     TMDD Interns 25'
 */
require '../../../../config/ims-tmdd.php';

try {
    // Create activity_logs table
    /**
     * @var \PDOStatement $createActivityLogsTable
     * @brief Prepared statement for creating the activity_logs table.
     *
     * This statement creates the activity_logs table if it does not already exist.
     */
    $createActivityLogsTable = $pdo->prepare("
        CREATE TABLE IF NOT EXISTS activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            module_id INT NOT NULL,
            activity_type VARCHAR(50) NOT NULL,
            description TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (module_id) REFERENCES modules(id)
        )
    ");
    $createActivityLogsTable->execute();
    echo "Activity logs table created successfully<br>";

    // Create notifications table
    /**
     * @var \PDOStatement $createNotificationsTable
     * @brief Prepared statement for creating the notifications table.
     *
     * This statement creates the notifications table if it does not already exist.
     */
    $createNotificationsTable = $pdo->prepare("
        CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            module_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (module_id) REFERENCES modules(id)
        )
    ");
    $createNotificationsTable->execute();
    echo "Notifications table created successfully<br>";

    // Insert some sample notifications
    /**
     * @var \PDOStatement $insertSampleNotifications
     * @brief Prepared statement for inserting sample notifications.
     *
     * This statement inserts sample data into the notifications table.
     */
    $insertSampleNotifications = $pdo->prepare("
        INSERT INTO notifications (module_id, title, message, priority) VALUES
        (1, 'System Update', 'The system has been updated to the latest version.', 'low'),
        (1, 'New Feature', 'New dashboard features are now available.', 'medium'),
        (1, 'Important Notice', 'Please update your profile information.', 'high')
    ");
    $insertSampleNotifications->execute();
    echo "Sample notifications inserted successfully<br>";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 