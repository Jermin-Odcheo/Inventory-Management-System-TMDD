<?php
require '../../../../config/ims-tmdd.php';

try {
    // Create activity_logs table
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