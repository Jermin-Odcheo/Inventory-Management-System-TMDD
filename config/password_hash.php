<?php
/**
 * Password Hash Module
 *
 * This file provides secure password hashing and verification functionality for the system. It handles password encryption, validation, and security measures. The module ensures proper password management and maintains security standards for user authentication.
 *
 * @package    InventoryManagementSystem
 * @subpackage Configuration
 * @author     TMDD Interns 25'
 */
require 'ims-tmdd.php'; // Your DB connection file

// Fetch all users with plain text passwords (be cautious and test this on a backup first)
$stmt = $pdo->query("SELECT User_ID, Password FROM users");
$users = $stmt->fetchAll();
/**
 * @var array $users The users with plain text passwords.
 */
foreach ($users as $user) {
    // Check if the password is already hashed (you might add a condition if you can distinguish them)
    // For example, if your plain text passwords are known to be shorter than 60 characters (bcrypt hash length)
    if (strlen($user['Password']) < 60) {
        $hashedPassword = password_hash($user['Password'], PASSWORD_DEFAULT);
        
        // Update the user record with the hashed password
        $updateStmt = $pdo->prepare("UPDATE users SET Password = ? WHERE User_ID = ?");
        $updateStmt->execute([$hashedPassword, $user['User_ID']]);
    }
}

echo "Migration complete!";
?>
