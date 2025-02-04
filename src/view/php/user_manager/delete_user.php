<?php
session_start();
require_once('../../../../config/ims-tmdd.php');

// Check for proper privileges here.

if (isset($_GET['id'])) {
    $userID = $_GET['id'];

    // Instead of deleting from user_roles, you could either:
    //   (1) Leave user_roles as-is, preserving them for potential restore.
    //   (2) Soft-delete user roles (if the table supports it).
    // Remove or modify this statement accordingly:
    // $stmt = $pdo->prepare("DELETE FROM user_roles WHERE User_ID = ?");
    // $stmt->execute([$userID]);

    // Soft-delete the user by setting is_deleted = 1 instead of physically removing.
    $stmt = $pdo->prepare("UPDATE users SET is_deleted = 1 WHERE User_ID = ?");
    $stmt->execute([$userID]);
}

header("Location: user_management.php");
exit();
?>
